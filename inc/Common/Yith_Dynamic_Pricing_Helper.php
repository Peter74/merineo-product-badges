<?php
/**
 * YITH Dynamic Pricing & Discounts helper.
 *
 * @package Merineo_Product_Badges
 *
 * @since 1.0.8
 */

declare(strict_types=1);

namespace Merineo\ProductBadges\Common;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Helper for reading YITH rules and preparing lightweight promo lookups.
 *
 * Notes:
 * - We support catalog pricing rules:
 *   bulk, discount_whole (Global discount), category_discount.
 * - We also support promo rules (eligible/promo badges):
 *   bogo, special_offer, gift_products.
 * - Admin UI should show sections for existing rules even if not currently valid (scheduled),
 *   but we expose current validity via is_valid() for informational notes.
 */
final class Yith_Dynamic_Pricing_Helper {

	/**
	 * Supported YITH discount modes.
	 *
	 * @return string[]
	 */
	public static function get_supported_modes(): array {
		return [
			'bulk',
			'discount_whole',
			'category_discount',
			'bogo',
			'special_offer',
			'gift_products',
		];
	}

	/**
	 * Supported promo-only modes (eligible/promo badges).
	 *
	 * @return string[]
	 */
	public static function get_promo_modes(): array {
		return [
			'bogo',
			'special_offer',
			'gift_products',
		];
	}

	/**
	 * Check if YITH Dynamic Pricing & Discounts API is available (admin-safe).
	 *
	 * We intentionally do NOT require frontend classes here, because admin screens
	 * may not load them, while rule functions are still available.
	 *
	 * @return bool
	 */
	public static function is_available(): bool {
		return function_exists( 'ywdpd_get_price_rules' );
	}

	/**
	 * Build option key for automatic badge config.
	 *
	 * @param string $rule_id Rule ID.
	 * @return string
	 */
	public static function make_option_key( string $rule_id ): string {
		$rule_id = preg_replace( '/\D+/', '', $rule_id );
		return 'yith_rule_' . $rule_id;
	}

	/**
	 * Get rules for supported rule types.
	 *
	 * We return also current validity state (schedule/conditions/user), so admin UI can show info.
	 *
	 * @return array<int, array{id:string,name:string,mode:string,priority:int,is_valid:bool}>
	 */
	public static function get_rules(): array {
		if ( ! self::is_available() ) {
			return [];
		}

		$rules = ywdpd_get_price_rules( self::get_supported_modes() );
		if ( ! is_array( $rules ) || empty( $rules ) ) {
			return [];
		}

		$out = [];

		foreach ( $rules as $rule ) {
			if ( ! is_object( $rule ) || ! method_exists( $rule, 'get_discount_mode' ) ) {
				continue;
			}

			$mode = (string) $rule->get_discount_mode();
			if ( ! in_array( $mode, self::get_supported_modes(), true ) ) {
				continue;
			}

			$rule_id = '';
			if ( method_exists( $rule, 'get_id' ) ) {
				$rule_id = (string) $rule->get_id();
			} elseif ( property_exists( $rule, 'id' ) ) {
				$rule_id = (string) $rule->id;
			}
			if ( '' === $rule_id ) {
				continue;
			}

			$name = '';
			if ( method_exists( $rule, 'get_name' ) ) {
				$name = (string) $rule->get_name();
			}

			$priority = 0;
			if ( method_exists( $rule, 'get_priority' ) ) {
				$priority = (int) $rule->get_priority();
			}

			$is_valid = false;
			if ( method_exists( $rule, 'is_valid' ) ) {
				$is_valid = ( true === $rule->is_valid() );
			}

			$out[] = [
				'id'       => (string) $rule_id,
				'name'     => ( '' !== trim( $name ) )
					? $name
					/* translators: %s: YITH rule ID. */
					: sprintf( __( 'YITH rule #%s', 'merineo-product-badges' ), $rule_id ),
				'mode'     => $mode,
				'priority' => $priority,
				'is_valid' => $is_valid,
			];
		}

		usort(
			$out,
			static function ( array $a, array $b ): int {
				return $a['priority'] <=> $b['priority'];
			}
		);

		return $out;
	}
}
