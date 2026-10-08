<?php
/**
 * Persian-aware text helpers.
 *
 * @package Yuniq\Ai
 * @author  Yegane Norouzi <https://github.com/Yeganenorouzi>
 */

namespace Yuniq\Ai\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalization and measurement for mixed Persian/Arabic/Latin text.
 *
 * Persian is typed inconsistently: the same word may use the Arabic ي or
 * the Persian ی, the Arabic ك or the Persian ک, a zero-width non-joiner
 * or a plain space, and Persian, Arabic-Indic or ASCII digits. Indexing
 * and searching a normalized copy makes all those spellings match, which
 * is also what keeps the knowledge base query off its slow LIKE path.
 */
final class Text {

	/**
	 * Zero-width non-joiner (نیم‌فاصله).
	 */
	const ZWNJ = "\xE2\x80\x8C";

	/**
	 * Character folding applied to every searchable string.
	 *
	 * @var array<string,string>
	 */
	private static $folding = array(
		// Arabic letter forms to their Persian equivalents.
		'ي' => 'ی',
		'ك' => 'ک',
		'ة' => 'ه',
		'ۀ' => 'ه',
		'أ' => 'ا',
		'إ' => 'ا',
		'آ' => 'ا',
		'ٱ' => 'ا',
		'ؤ' => 'و',
		'ئ' => 'ی',
		'ى' => 'ی',
		// Persian digits.
		'۰' => '0',
		'۱' => '1',
		'۲' => '2',
		'۳' => '3',
		'۴' => '4',
		'۵' => '5',
		'۶' => '6',
		'۷' => '7',
		'۸' => '8',
		'۹' => '9',
		// Arabic-Indic digits.
		'٠' => '0',
		'١' => '1',
		'٢' => '2',
		'٣' => '3',
		'٤' => '4',
		'٥' => '5',
		'٦' => '6',
		'٧' => '7',
		'٨' => '8',
		'٩' => '9',
		// Punctuation that would otherwise split tokens differently.
		'،' => ' ',
		'؛' => ' ',
		'؟' => ' ',
		'«' => ' ',
		'»' => ' ',
		'ـ' => '',
	);

	/**
	 * Persian and English words too common to be useful as search terms.
	 *
	 * @var string[]
	 */
	private static $stop_words = array(
		// Persian.
		'و', 'در', 'به', 'از', 'که', 'این', 'را', 'با', 'های', 'برای', 'آن', 'یک',
		'شود', 'شده', 'است', 'هست', 'هستم', 'هستید', 'بود', 'باشد', 'می', 'خود',
		'ما', 'شما', 'من', 'او', 'آنها', 'تا', 'یا', 'هم', 'نیز', 'اگر', 'ولی',
		'اما', 'چون', 'چه', 'چی', 'کدام', 'کجا', 'چرا', 'چطور', 'چگونه', 'کنید',
		'کنم', 'کند', 'دارد', 'دارم', 'دارید', 'داره', 'بگو', 'بده', 'لطفا', 'لطفاً',
		'سلام', 'ممنون', 'میشه', 'میشود', 'باید', 'هر', 'همه', 'بین', 'روی', 'بر',
		'داری', 'دارین', 'هایی', 'ها', 'هست', 'هستش', 'نیست', 'میخوام', 'میخواستم', 'میخواهم',
		'بله', 'خیر', 'موجود', 'مدل', 'مختلف', 'دو', 'سه', 'بپرسم', 'بگید', 'بگین', 'بدید', 'بدین', 'کنین', 'چند', 'چنده', 'ایا', 'خب', 'پس', 'الان',
		// English.
		'the', 'a', 'an', 'is', 'are', 'was', 'were', 'what', 'how', 'can', 'you',
		'me', 'my', 'your', 'do', 'does', 'for', 'to', 'of', 'and', 'or', 'in',
		'on', 'with', 'about', 'please', 'tell', 'it', 'this', 'that', 'be',
	);

	/**
	 * Fold a string into its searchable form.
	 *
	 * The result is only ever stored in the search column or used to build
	 * a query — the original text is kept intact for display.
	 *
	 * @param string $text Raw text.
	 * @return string
	 */
	public static function normalize( $text ) {
		$text = (string) $text;

		if ( '' === $text ) {
			return '';
		}

		$text = strtr( $text, self::$folding );

		// A ZWNJ joins parts of one word; a space lets each part match too.
		$text = str_replace( self::ZWNJ, ' ', $text );

		// Arabic diacritics carry no meaning for matching.
		$text = preg_replace( '/[\x{064B}-\x{065F}\x{0670}]/u', '', $text );

		$text = function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
		$text = preg_replace( '/\s+/u', ' ', $text );

		return trim( (string) $text );
	}

	/**
	 * Character count, correct for multibyte text.
	 *
	 * @param string $text Text to measure.
	 * @return int
	 */
	public static function length( $text ) {
		$text = (string) $text;

		return function_exists( 'mb_strlen' ) ? mb_strlen( $text, 'UTF-8' ) : strlen( $text );
	}

	/**
	 * Truncate without splitting a multibyte character in half.
	 *
	 * @param string $text     Text to cut.
	 * @param int    $length   Maximum characters to keep.
	 * @param string $trail    Appended when the text was actually cut.
	 * @return string
	 */
	public static function truncate( $text, $length, $trail = '...' ) {
		$text = (string) $text;

		if ( self::length( $text ) <= $length ) {
			return $text;
		}

		$cut = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $length, 'UTF-8' ) : substr( $text, 0, $length );

		return $cut . $trail;
	}

	/**
	 * Split a query into meaningful search tokens.
	 *
	 * Stop words and one-character fragments are dropped, and the list is
	 * capped so a long question cannot turn into a long run of queries.
	 *
	 * @param string $text  Raw query.
	 * @param int    $limit Maximum tokens returned.
	 * @return string[] Normalized, de-duplicated tokens.
	 */
	public static function tokens( $text, $limit = 4 ) {
		$normalized = self::normalize( $text );

		if ( '' === $normalized ) {
			return array();
		}

		$parts  = preg_split( '/[^\p{L}\p{N}]+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY );
		$tokens = array();

		foreach ( (array) $parts as $part ) {
			if ( self::length( $part ) < 2 || in_array( $part, self::$stop_words, true ) ) {
				continue;
			}

			$tokens[ $part ] = true;

			if ( count( $tokens ) >= $limit ) {
				break;
			}
		}

		return array_keys( $tokens );
	}

	/**
	 * Words that mean the same thing in Persian and Latin script. A shop
	 * lists "iPhone 16"; its visitors type «آیفون ۱۶». Without this the
	 * two never meet.
	 *
	 * @var array<int,string[]>
	 */
	private static $synonym_groups = array(
		array( 'آیفون', 'iphone' ),
		array( 'اپل', 'apple' ),
		array( 'سامسونگ', 'samsung' ),
		array( 'گلکسی', 'galaxy' ),
		array( 'شیائومی', 'xiaomi' ),
		array( 'هواوی', 'huawei' ),
		array( 'نوکیا', 'nokia' ),
		array( 'آنر', 'honor' ),
		array( 'ریلمی', 'realme' ),
		array( 'پوکو', 'poco' ),
		array( 'ردمی', 'redmi' ),
		array( 'ایسوس', 'asus' ),
		array( 'لنوو', 'lenovo' ),
		array( 'سونی', 'sony' ),
		array( 'مک‌بوک', 'macbook', 'مکبوک' ),
		array( 'آیپد', 'ipad' ),
		array( 'ایرپاد', 'airpods', 'airpod' ),
		array( 'لپتاپ', 'laptop', 'لپ‌تاپ' ),
		array( 'موبایل', 'mobile', 'گوشی' ),
		array( 'تبلت', 'tablet' ),
		array( 'هدفون', 'headphone', 'هدست', 'headset' ),
		array( 'ساعت', 'watch' ),
		array( 'پرو', 'pro' ),
		array( 'مکس', 'max' ),
		array( 'پلاس', 'plus' ),
		array( 'مینی', 'mini' ),
		array( 'اولترا', 'ultra' ),
		array( 'گیگ', 'gb', 'گیگابایت' ),
		array( 'ترابایت', 'tb' ),
	);

	/**
	 * The search words of a question, each with how much it should count.
	 *
	 * Words from the question weigh 2. Words borrowed from the earlier
	 * conversation weigh 1: enough to keep a follow-up question on the
	 * same subject, not enough to outvote what was actually asked.
	 *
	 * @param string $query   The visitor's question.
	 * @param string $context Earlier turns, optional.
	 * @param array  $custom  Extra synonym groups, each an array of words.
	 * @return array<string,int> Normalized token => weight.
	 */
	public static function weighted_tokens( $query, $context = '', array $custom = array() ) {
		$weights = array();

		foreach ( self::tokens( $query, 8 ) as $token ) {
			$weights[ $token ] = 2;
		}

		foreach ( self::tokens( $context, 8 ) as $token ) {
			if ( ! isset( $weights[ $token ] ) ) {
				$weights[ $token ] = 1;
			}
		}

		if ( ! $weights ) {
			return array();
		}

		$map = self::synonym_map( $custom );

		foreach ( $weights as $token => $weight ) {
			if ( empty( $map[ $token ] ) ) {
				continue;
			}

			foreach ( $map[ $token ] as $alias ) {
				if ( ! isset( $weights[ $alias ] ) ) {
					$weights[ $alias ] = $weight;
				}
			}
		}

		// Each token costs two LIKE comparisons per row.
		return array_slice( $weights, 0, 18, true );
	}

	/**
	 * Lookup from a normalized word to the other words in its group.
	 *
	 * @param array $custom Extra groups from the settings screen.
	 * @return array<string,string[]>
	 */
	private static function synonym_map( array $custom ) {
		$map = array();

		foreach ( array_merge( self::$synonym_groups, $custom ) as $group ) {
			$words = array();

			foreach ( (array) $group as $word ) {
				// Matching is by substring, so a two-word alias is searched as a phrase.
				$word = self::normalize( $word );

				if ( self::length( $word ) >= 2 ) {
					$words[ $word ] = true;
				}
			}

			$words = array_keys( $words );

			foreach ( $words as $word ) {
				$others       = array_values( array_diff( $words, array( $word ) ) );
				$map[ $word ] = isset( $map[ $word ] ) ? array_values( array_unique( array_merge( $map[ $word ], $others ) ) ) : $others;
			}
		}

		return $map;
	}

	/**
	 * Parse the synonyms textarea: one group per line, words separated by
	 * `=` or a comma, e.g. `کتونی = کفش ورزشی, sneaker`.
	 *
	 * @param string $text Raw setting value.
	 * @return array<int,string[]>
	 */
	public static function parse_synonyms( $text ) {
		$groups = array();

		foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $line ) {
			$words = array_values( array_filter( array_map( 'trim', preg_split( '/[=,،]+/u', $line ) ), 'strlen' ) );

			if ( count( $words ) >= 2 ) {
				$groups[] = array_slice( $words, 0, 8 );
			}

			if ( count( $groups ) >= 100 ) {
				break;
			}
		}

		return $groups;
	}

	/**
	 * Reduce a client-supplied session id to the shape the plugin issues:
	 * letters, digits and dashes, at most 64 characters (the column width).
	 *
	 * @param mixed $raw Value from the request.
	 * @return string Empty when nothing usable is left.
	 */
	public static function session_id( $raw ) {
		$clean = preg_replace( '/[^A-Za-z0-9\-]/', '', is_scalar( $raw ) ? (string) $raw : '' );
		$clean = substr( (string) $clean, 0, 64 );

		return strlen( $clean ) >= 8 ? $clean : '';
	}

	/**
	 * Persian and Arabic digits as Latin ones, for phone numbers and the like.
	 *
	 * @param string $text Raw text.
	 * @return string
	 */
	public static function latin_digits( $text ) {
		return strtr(
			(string) $text,
			array(
				'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
				'۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
				'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
				'٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
			)
		);
	}

	/**
	 * A stored MySQL datetime in the site's own date format. Jalali
	 * calendar plugins hook the same formatter, so they apply here too.
	 *
	 * @param string $mysql_date `Y-m-d H:i:s`, or empty.
	 * @return string
	 */
	public static function human_date( $mysql_date ) {
		if ( empty( $mysql_date ) || '0000-00-00 00:00:00' === $mysql_date ) {
			return '';
		}

		return (string) mysql2date( get_option( 'date_format' ) . ' H:i', $mysql_date );
	}

	/**
	 * Whether a normalized word is a stop word.
	 *
	 * @param string $word Normalized word.
	 * @return bool
	 */
	public static function is_stop_word( $word ) {
		return in_array( $word, self::$stop_words, true );
	}
}
