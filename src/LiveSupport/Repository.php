<?php
/**
 * Human-handoff conversations and their message transcript.
 *
 * @package Yuniq\Ai
 * @author  Yegane Norouzi <https://github.com/Yeganenorouzi>
 */

namespace Yuniq\Ai\LiveSupport;

use Yuniq\Ai\Setup\Schema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A conversation is one row keyed by `session_id` tracking handoff
 * status/assignment; the transcript itself lives in a separate table that
 * only starts filling in once a conversation is first escalated.
 */
final class Repository {

	/**
	 * Fully prefixed conversations table name.
	 *
	 * @var string
	 */
	private $conversations_table;

	/**
	 * Fully prefixed messages table name.
	 *
	 * @var string
	 */
	private $messages_table;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->conversations_table = Schema::table( Schema::CONVERSATIONS );
		$this->messages_table      = Schema::table( Schema::MESSAGES );
	}

	/**
	 * Fetch a conversation by session id.
	 *
	 * @param string $session_id Session identifier.
	 * @return array|null
	 */
	public function get_conversation( $session_id ) {
		global $wpdb;

		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "SELECT * FROM {$this->conversations_table} WHERE session_id = %s", $session_id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		return $row ? $row : null;
	}

	/**
	 * Fetch a conversation, creating a bare `bot`-status row if none exists.
	 *
	 * @param string $session_id Session identifier.
	 * @return array
	 */
	public function get_or_create_conversation( $session_id ) {
		$existing = $this->get_conversation( $session_id );

		if ( $existing ) {
			return $existing;
		}

		global $wpdb;

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->conversations_table,
			array(
				'session_id' => sanitize_text_field( $session_id ),
				'status'     => 'bot',
				'created_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s' )
		);

		return $this->get_conversation( $session_id );
	}

	/**
	 * Escalate a conversation to `pending`, backfilling prior AI turns into
	 * the transcript the first time this session is ever escalated.
	 *
	 * @param string $session_id Session identifier.
	 * @param string $name       Visitor-supplied name, optional.
	 * @param string $contact    Visitor-supplied phone/email, optional.
	 * @return array Updated conversation row.
	 */
	public function escalate( $session_id, $name = '', $contact = '' ) {
		$conversation        = $this->get_or_create_conversation( $session_id );
		$is_first_escalation = 'bot' === $conversation['status'];

		global $wpdb;

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->conversations_table,
			array(
				'status'           => 'pending',
				'visitor_name'     => sanitize_text_field( $name ),
				'visitor_contact'  => sanitize_text_field( $contact ),
				'unread_for_admin' => 1,
				'last_message_at'  => current_time( 'mysql' ),
			),
			array( 'session_id' => $session_id ),
			array( '%s', '%s', '%s', '%d', '%s' ),
			array( '%s' )
		);

		if ( $is_first_escalation ) {
			$this->backfill_from_analytics( $session_id );
		}

		return $this->get_conversation( $session_id );
	}

	/**
	 * Copy this session's prior AI turns into the transcript table, so an
	 * agent claiming the conversation sees what already happened instead of
	 * an empty thread.
	 *
	 * @param string $session_id Session identifier.
	 * @return void
	 */
	private function backfill_from_analytics( $session_id ) {
		global $wpdb;

		$analytics_table = Schema::table( Schema::ANALYTICS );

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT user_question, ai_response, created_at FROM {$analytics_table} WHERE session_id = %s ORDER BY created_at ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$session_id
			),
			ARRAY_A
		);

		foreach ( (array) $rows as $row ) {
			if ( '' !== trim( (string) $row['user_question'] ) ) {
				$this->insert_message( $session_id, 'user', $row['user_question'], $row['created_at'] );
			}

			if ( '' !== trim( (string) $row['ai_response'] ) ) {
				$this->insert_message( $session_id, 'assistant', $row['ai_response'], $row['created_at'] );
			}
		}
	}

	/**
	 * Append a message and bump the conversation's unread/last-activity state.
	 *
	 * @param string $session_id Session identifier.
	 * @param string $role       `user`, `assistant`, `agent` or `system`.
	 * @param string $content    Message text.
	 * @return int|false Row id.
	 */
	public function add_message( $session_id, $role, $content ) {
		$id = $this->insert_message( $session_id, $role, $content );

		global $wpdb;

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->conversations_table,
			array(
				'last_message_at'    => current_time( 'mysql' ),
				'unread_for_admin'   => 'agent' === $role ? 0 : 1,
				'unread_for_visitor' => 'agent' === $role ? 1 : 0,
			),
			array( 'session_id' => $session_id ),
			array( '%s', '%d', '%d' ),
			array( '%s' )
		);

		return $id;
	}

	/**
	 * Raw insert into the transcript table, optionally backdated.
	 *
	 * @param string      $session_id Session identifier.
	 * @param string      $role       Message role.
	 * @param string      $content    Message text.
	 * @param string|null $created_at Explicit timestamp, used only by the analytics backfill.
	 * @return int|false Row id.
	 */
	private function insert_message( $session_id, $role, $content, $created_at = null ) {
		global $wpdb;

		$data    = array(
			'session_id' => sanitize_text_field( $session_id ),
			'role'       => sanitize_key( $role ),
			'content'    => wp_kses_post( $content ),
		);
		$formats = array( '%s', '%s', '%s' );

		if ( $created_at ) {
			$data['created_at'] = $created_at;
			$formats[]          = '%s';
		}

		$wpdb->insert( $this->messages_table, $data, $formats ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		return $wpdb->insert_id ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Messages for one conversation, oldest first.
	 *
	 * @param string $session_id Session identifier.
	 * @param int    $after_id   Only return rows with a higher id (for polling).
	 * @return array
	 */
	public function get_messages( $session_id, $after_id = 0 ) {
		global $wpdb;

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT id, role, content, created_at FROM {$this->messages_table} WHERE session_id = %s AND id > %d ORDER BY id ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$session_id,
				$after_id
			),
			ARRAY_A
		);

		return $rows ? $rows : array();
	}

	/**
	 * Conversations for the admin inbox.
	 *
	 * @param string|null $status Filter by status, or null for every escalated conversation.
	 * @return array
	 */
	public function get_conversations( $status = null ) {
		global $wpdb;

		if ( $status ) {
			$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					"SELECT * FROM {$this->conversations_table} WHERE status = %s ORDER BY last_message_at DESC, created_at DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$status,
					100
				),
				ARRAY_A
			);
		} else {
			$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				"SELECT * FROM {$this->conversations_table} WHERE status != 'bot' ORDER BY last_message_at DESC, created_at DESC LIMIT 100", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				ARRAY_A
			);
		}

		return $rows ? $rows : array();
	}

	/**
	 * Assign a conversation to the agent claiming it.
	 *
	 * @param string $session_id     Session identifier.
	 * @param int    $agent_user_id  WP user id of the claiming admin.
	 * @return void
	 */
	public function claim( $session_id, $agent_user_id ) {
		global $wpdb;

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->conversations_table,
			array(
				'status'           => 'active',
				'assigned_agent'   => absint( $agent_user_id ),
				'unread_for_admin' => 0,
			),
			array( 'session_id' => $session_id ),
			array( '%s', '%d', '%d' ),
			array( '%s' )
		);
	}

	/**
	 * Close a conversation and hand control back to the AI.
	 *
	 * @param string $session_id Session identifier.
	 * @return void
	 */
	public function resolve( $session_id ) {
		global $wpdb;

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->conversations_table,
			array( 'status' => 'resolved' ),
			array( 'session_id' => $session_id ),
			array( '%s' ),
			array( '%s' )
		);
	}

	/**
	 * Clear the admin-side unread flag.
	 *
	 * @param string $session_id Session identifier.
	 * @return void
	 */
	public function mark_read_admin( $session_id ) {
		global $wpdb;

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->conversations_table,
			array( 'unread_for_admin' => 0 ),
			array( 'session_id' => $session_id ),
			array( '%d' ),
			array( '%s' )
		);
	}

	/**
	 * Clear the visitor-side unread flag.
	 *
	 * @param string $session_id Session identifier.
	 * @return void
	 */
	public function mark_read_visitor( $session_id ) {
		global $wpdb;

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->conversations_table,
			array( 'unread_for_visitor' => 0 ),
			array( 'session_id' => $session_id ),
			array( '%d' ),
			array( '%s' )
		);
	}

	/**
	 * Conversations waiting for an agent to claim them — the admin-menu badge count.
	 *
	 * @return int
	 */
	public function count_open() {
		global $wpdb;

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->conversations_table} WHERE status = 'pending'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	}
}
