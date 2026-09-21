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
	 * Whether a normalized word is a stop word.
	 *
	 * @param string $word Normalized word.
	 * @return bool
	 */
	public static function is_stop_word( $word ) {
		return in_array( $word, self::$stop_words, true );
	}
}
