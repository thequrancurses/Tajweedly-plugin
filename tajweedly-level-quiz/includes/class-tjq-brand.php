<?php
defined( 'ABSPATH' ) || exit;

/**
 * Reads the site's homepage and extracts brand identity (colours, font, logo),
 * so the quiz looks like the rest of the website without manual design work.
 */
class TJQ_Brand {

	public static function detect( $url = '' ) {
		$url = $url ? $url : TJQ_Settings::get( 'brand_source_url' );
		$url = $url ? $url : home_url( '/' );

		$res = wp_remote_get( $url, array( 'timeout' => 12, 'redirection' => 3, 'user-agent' => 'TajweedlyQuiz/' . TJQ_VERSION ) );
		if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
			return new WP_Error( 'tjq_brand_fetch', 'Could not read the homepage.' );
		}
		$html = wp_remote_retrieve_body( $res );
		$base = $url;
		$css  = '';

		// Inline <style> blocks.
		if ( preg_match_all( '#<style[^>]*>(.*?)</style>#is', $html, $m ) ) {
			$css .= implode( "\n", $m[1] );
		}
		// Up to 4 linked stylesheets.
		if ( preg_match_all( '#<link[^>]+rel=["\']stylesheet["\'][^>]*>#i', $html, $links ) ) {
			$n = 0;
			foreach ( $links[0] as $tag ) {
				if ( $n >= 4 || ! preg_match( '#href=["\']([^"\']+)["\']#i', $tag, $h ) ) {
					continue;
				}
				$href = self::abs( $h[1], $base );
				if ( false !== strpos( $href, 'fonts.googleapis.com' ) ) {
					continue;
				}
				$r = wp_remote_get( $href, array( 'timeout' => 10 ) );
				if ( ! is_wp_error( $r ) && 200 === (int) wp_remote_retrieve_response_code( $r ) ) {
					$css .= "\n" . substr( wp_remote_retrieve_body( $r ), 0, 400000 );
					++$n;
				}
			}
		}

		$out = array(
			'primary_color' => '',
			'accent_color'  => '',
			'font_family'   => '',
			'font_url'      => '',
			'logo_url'      => '',
		);

		// 1) theme-color meta.
		if ( preg_match( '#<meta[^>]+name=["\']theme-color["\'][^>]+content=["\'](\#[0-9a-fA-F]{3,6})["\']#i', $html, $m ) ) {
			$out['primary_color'] = self::norm( $m[1] );
		}

		// 2) CSS custom properties that look like brand colours.
		$vars = array();
		if ( preg_match_all( '#--([a-z0-9-]*(?:primary|brand|main|accent|secondary)[a-z0-9-]*)\s*:\s*(\#[0-9a-fA-F]{3,8})#i', $css, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $x ) {
				$vars[ strtolower( $x[1] ) ] = self::norm( $x[2] );
			}
		}
		foreach ( $vars as $name => $hex ) {
			if ( ! $out['primary_color'] && false !== strpos( $name, 'primary' ) ) {
				$out['primary_color'] = $hex;
			}
			if ( ! $out['accent_color'] && ( false !== strpos( $name, 'accent' ) || false !== strpos( $name, 'secondary' ) ) ) {
				$out['accent_color'] = $hex;
			}
		}
		if ( ! $out['primary_color'] && $vars ) {
			$out['primary_color'] = reset( $vars );
		}

		// 3) Fallback: most used saturated colour in the CSS.
		if ( ! $out['primary_color'] || ! $out['accent_color'] ) {
			$ranked = self::ranked_colors( $css . $html );
			if ( ! $out['primary_color'] && $ranked ) {
				$out['primary_color'] = array_shift( $ranked );
			} elseif ( $out['primary_color'] ) {
				$ranked = array_values( array_diff( $ranked, array( $out['primary_color'] ) ) );
			}
			if ( ! $out['accent_color'] && $ranked ) {
				$out['accent_color'] = reset( $ranked );
			}
		}

		// Logo: og:image, apple-touch-icon, icon, or an <img> with "logo" in its src.
		$patterns = array(
			'#<img[^>]+src=["\']([^"\']*logo[^"\']*)["\']#i',
			'#<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']#i',
			'#<link[^>]+rel=["\'](?:apple-touch-icon|icon|shortcut icon)["\'][^>]+href=["\']([^"\']+)["\']#i',
		);
		foreach ( $patterns as $p ) {
			if ( preg_match( $p, $html, $m ) ) {
				$out['logo_url'] = self::abs( $m[1], $base );
				break;
			}
		}

		// Fonts: Google Fonts link first, then the body font-family rule.
		if ( preg_match( '#href=["\'](https://fonts\.googleapis\.com/css[^"\']+)["\']#i', $html, $m ) ) {
			$out['font_url'] = html_entity_decode( $m[1] );
			if ( preg_match( '#family=([^&:]+)#', $out['font_url'], $f ) ) {
				$out['font_family'] = "'" . str_replace( '+', ' ', urldecode( $f[1] ) ) . "', sans-serif";
			}
		}
		if ( ! $out['font_family'] && preg_match( '#body\s*\{[^}]*font-family\s*:\s*([^;}]+)#i', $css, $m ) ) {
			$out['font_family'] = trim( $m[1] );
		}

		return array_filter( $out );
	}

	private static function abs( $href, $base ) {
		$href = html_entity_decode( trim( $href ) );
		if ( preg_match( '#^https?://#i', $href ) ) {
			return $href;
		}
		$p = wp_parse_url( $base );
		if ( 0 === strpos( $href, '//' ) ) {
			return $p['scheme'] . ':' . $href;
		}
		$root = $p['scheme'] . '://' . $p['host'] . ( isset( $p['port'] ) ? ':' . $p['port'] : '' );
		if ( 0 === strpos( $href, '/' ) ) {
			return $root . $href;
		}
		return trailingslashit( $root . ( isset( $p['path'] ) ? dirname( $p['path'] . 'x' ) : '' ) ) . $href;
	}

	private static function norm( $hex ) {
		$hex = strtolower( $hex );
		if ( 4 === strlen( $hex ) ) {
			$hex = '#' . $hex[1] . $hex[1] . $hex[2] . $hex[2] . $hex[3] . $hex[3];
		}
		return substr( $hex, 0, 7 );
	}

	/** Rank hex colours by frequency, ignoring greys/near-white/near-black. */
	private static function ranked_colors( $text ) {
		$count = array();
		if ( preg_match_all( '#\#[0-9a-fA-F]{6}\b|\#[0-9a-fA-F]{3}\b#', $text, $m ) ) {
			foreach ( $m[0] as $hex ) {
				$hex = self::norm( $hex );
				$r   = hexdec( substr( $hex, 1, 2 ) );
				$g   = hexdec( substr( $hex, 3, 2 ) );
				$b   = hexdec( substr( $hex, 5, 2 ) );
				$max = max( $r, $g, $b );
				$min = min( $r, $g, $b );
				if ( ( $max - $min ) < 40 || $max < 40 || $min > 225 ) {
					continue; // grey / black / white.
				}
				$count[ $hex ] = isset( $count[ $hex ] ) ? $count[ $hex ] + 1 : 1;
			}
		}
		arsort( $count );
		return array_slice( array_keys( $count ), 0, 6 );
	}
}
