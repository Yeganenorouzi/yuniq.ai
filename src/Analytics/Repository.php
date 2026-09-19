<?php
/**
 * Conversation logging and usage metrics.
 *
 * @package Yuniq\Ai
 * @author  Yegane Norouzi <https://github.com/Yeganenorouzi>
 */

namespace Yuniq\Ai\Analytics;

use Yuniq\Ai\Setup\Schema;
use Yuniq\Ai\Support\Text;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores each conversation turn and summarizes usage.
 */
final class Repository {

	/**
	 * Fully prefixed table name.
	 *
	 * @var string
	 */
	private $table;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->table = Schema::table( Schema::ANALYTICS );
	}

	/**
	 * Record one question and its answer.
	 *
	 * @param string $session_id    Session identifier.
	 * @param string $user_question The visitor's message.
	 * @param string $ai_response   The assistant's reply.
	 * @param array  $extra         topics, response_time, tokens_used.
	 * @return int|false Row id.
	 */
	public function log( $session_id, $user_question, $ai_response = '', array $extra = array() ) {
		global $wpdb;

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->table,
			array(
				'session_id'    => sanitize_text_field( $session_id ),
				'user_question' => sanitize_textarea_field( $user_question ),
				'ai_response'   => wp_kses_post( $ai_response ),
				'topics'        => isset( $extra['topics'] ) ? sanitize_text_field( $extra['topics'] ) : '',
				'response_time' => isset( $extra['response_time'] ) ? (float) $extra['response_time'] : 0,
				'tokens_used'   => isset( $extra['tokens_used'] ) ? (int) $extra['tokens_used'] : 0,
			),
			array( '%s', '%s', '%s', '%s', '%f', '%d' )
		);

		return $wpdb->insert_id ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Distinct sessions recorded.
	 *
	 * @return int
	 */
	public function get_total_conversations() {
		global $wpdb;

		return (int) $wpdb->get_var( "SELECT COUNT(DISTINCT session_id) FROM {$this->table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Total questions asked.
	 *
	 * @return int
	 */
	public function get_total_questions() {
		global $wpdb;

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Total turns that produced an answer.
	 *
	 * @return int
	 */
	public function get_total_responses() {
		global $wpdb;

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table} WHERE ai_response != ''" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Average seconds spent answering, across recent turns.
	 *
	 * @return float
	 */
	public function get_average_response_time() {
		global $wpdb;

		return round(
			(float) $wpdb->get_var( "SELECT AVG(response_time) FROM {$this->table} WHERE response_time > 0" ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
			2
		);
	}

	/**
	 * Frequent words across recent questions.
	 *
	 * Questions are normalized first, so «می‌خواهم» and «ميخواهم» count as
	 * the same word, and Persian stop words are dropped — without that the
	 * list fills up with «که», «برای» and «این».
	 *
	 * @param int $limit Maximum topics.
	 * @return array<string,int>
	 */
	public function get_popular_topics( $limit = 10 ) {
		global $wpdb;

		$questions = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "SELECT user_question FROM {$this->table} ORDER BY created_at DESC LIMIT %d", 200 )
		);

		$counts = array();

		foreach ( (array) $questions as $question ) {
			$normalized = Text::normalize( $question );
			$words      = preg_split( '/[^\p{L}\p{N}]+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY );

			foreach ( (array) $words as $word ) {
				if ( Text::length( $word ) < 3 || Text::is_stop_word( $word ) ) {
					continue;
				}

				$counts[ $word ] = isset( $counts[ $word ] ) ? $counts[ $word ] + 1 : 1;
			}
		}

		arsort( $counts );

		return array_slice( $counts, 0, $limit, true );
	}

	/**
	 * Most recent conversation turns.
	 *
	 * @param int $limit Maximum rows.
	 * @return array
	 */
	public function get_recent( $limit = 20 ) {
		global $wpdb;

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT id, session_id, user_question, LEFT(ai_response, 160) AS response_preview, tokens_used, response_time, created_at
				FROM {$this->table}
				ORDER BY created_at DESC
				LIMIT %d",
				$limit
			),
			ARRAY_A
		);

		return $rows ? $rows : array();
	}

	/**
	 * Everything the analytics screen needs, in one call.
	 *
	 * @return array
	 */
	public function get_summary() {
		return array(
			'total_conversations'   => $this->get_total_conversations(),
			'total_questions'       => $this->get_total_questions(),
			'total_responses'       => $this->get_total_responses(),
			'average_response_time' => $this->get_average_response_time(),
			'popular_topics'        => $this->get_popular_topics( 8 ),
		);
	}
}
