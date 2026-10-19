<?php
/**
 * Declarative settings framework (prototype, roadmap layer 3).
 *
 * @package WenpaiAdminUI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Wenpai_Admin_Settings' ) ) {
	/**
	 * One instance per option. Schema: id => [type, default, label, desc, choices, min, max, secret, sanitize].
	 * Types: toggle, select, radio, number, text, textarea, multicheck, map.
	 */
	class Wenpai_Admin_Settings {

		private static $registry = array();
		private $option;
		private $group;
		private $fields;

		public static function register( $option, array $fields, array $args = array() ) {
			$inst                       = new self( $option, $fields, $args );
			self::$registry[ $option ] = $inst;
			add_action( 'admin_init', array( $inst, 'register_setting' ) );
			return $inst;
		}

		public static function get_instance( $option ) {
			return isset( self::$registry[ $option ] ) ? self::$registry[ $option ] : null;
		}

		private function __construct( $option, array $fields, array $args ) {
			$this->option = $option;
			$this->group  = isset( $args['group'] ) ? $args['group'] : $option . '_group';
			$this->fields = array();
			foreach ( $fields as $id => $f ) {
				$this->fields[ $id ] = wp_parse_args(
					$f,
					array( 'type' => 'text', 'default' => '', 'label' => $id, 'desc' => '', 'choices' => array(), 'secret' => false )
				);
			}
		}

		public function register_setting() {
			register_setting(
				$this->group,
				$this->option,
				array( 'type' => 'array', 'default' => array(), 'show_in_rest' => false, 'sanitize_callback' => array( $this, 'sanitize' ) )
			);
		}

		public function group() {
			return $this->group;
		}

		public function fields() {
			return $this->fields;
		}

		public function defaults() {
			return wp_list_pluck( $this->fields, 'default' );
		}

		public function all() {
			$stored = get_option( $this->option, array() );
			return wp_parse_args( is_array( $stored ) ? $stored : array(), $this->defaults() );
		}

		public function get( $id ) {
			$all = $this->all();
			return isset( $all[ $id ] ) ? $all[ $id ] : null;
		}

		public function sanitize( $input ) {
			$input = is_array( $input ) ? $input : array();
			$out   = array();
			foreach ( $this->fields as $id => $f ) {
				$raw        = array_key_exists( $id, $input ) ? $input[ $id ] : null;
				$out[ $id ] = $this->sanitize_field( $f, $raw );
			}
			return $out;
		}

		private function sanitize_field( array $f, $v ) {
			if ( isset( $f['sanitize'] ) && is_callable( $f['sanitize'] ) ) {
				return call_user_func( $f['sanitize'], $v, $f );
			}
			switch ( $f['type'] ) {
				case 'toggle':
					return ! empty( $v );
				case 'number':
					$n = null === $v || '' === $v ? (int) $f['default'] : (int) $v;
					if ( isset( $f['min'] ) ) {
						$n = max( (int) $f['min'], $n );
					}
					if ( isset( $f['max'] ) ) {
						$n = min( (int) $f['max'], $n );
					}
					return $n;
				case 'select':
				case 'radio':
					$v = is_scalar( $v ) ? (string) $v : '';
					return array_key_exists( $v, $f['choices'] ) ? $v : $f['default'];
				case 'multicheck':
					$v = is_array( $v ) ? array_map( 'sanitize_key', $v ) : array();
					return empty( $f['choices'] ) ? array_values( $v ) : array_values( array_intersect( $v, array_keys( $f['choices'] ) ) );
				case 'map':
					$res = array();
					foreach ( ( is_array( $v ) ? $v : array() ) as $k => $val ) {
						$res[ sanitize_key( $k ) ] = sanitize_key( $val );
					}
					return $res;
				case 'textarea':
					return null === $v ? $f['default'] : sanitize_textarea_field( wp_unslash( $v ) );
				default:
					return null === $v ? $f['default'] : sanitize_text_field( wp_unslash( $v ) );
			}
		}

		public function export() {
			$data = $this->all();
			foreach ( $this->fields as $id => $f ) {
				if ( $f['secret'] ) {
					unset( $data[ $id ] );
				}
			}
			return $data;
		}

		public function import( array $data ) {
			$current = $this->all();
			$clean   = $this->sanitize( wp_parse_args( $data, $current ) );
			foreach ( $this->fields as $id => $f ) {
				if ( $f['secret'] ) {
					$clean[ $id ] = $current[ $id ];
				}
			}
			return update_option( $this->option, $clean );
		}

		public function render_field( $id ) {
			if ( ! isset( $this->fields[ $id ] ) ) {
				return;
			}
			$f    = $this->fields[ $id ];
			$v    = $this->get( $id );
			$name = $this->option . '[' . $id . ']';
			$fid  = 'wenpai-f-' . $this->option . '-' . $id;
			echo '<div class="wenpai-field wenpai-field--' . esc_attr( $f['type'] ) . '">';
			echo '<label class="wenpai-field__label" for="' . esc_attr( $fid ) . '">' . esc_html( $f['label'] ) . '</label>';
			switch ( $f['type'] ) {
				case 'toggle':
					echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="0">';
					echo '<input type="checkbox" role="switch" id="' . esc_attr( $fid ) . '" name="' . esc_attr( $name ) . '" value="1"' . checked( $v, true, false ) . '>';
					break;
				case 'select':
					echo '<select id="' . esc_attr( $fid ) . '" name="' . esc_attr( $name ) . '">';
					foreach ( $f['choices'] as $k => $lab ) {
						echo '<option value="' . esc_attr( $k ) . '"' . selected( $v, $k, false ) . '>' . esc_html( $lab ) . '</option>';
					}
					echo '</select>';
					break;
				case 'radio':
				case 'multicheck':
					$multi = 'multicheck' === $f['type'];
					echo '<fieldset id="' . esc_attr( $fid ) . '">';
					foreach ( $f['choices'] as $k => $lab ) {
						$on = $multi ? in_array( $k, (array) $v, true ) : ( (string) $v === (string) $k );
						echo '<label><input type="' . ( $multi ? 'checkbox' : 'radio' ) . '" name="' . esc_attr( $name . ( $multi ? '[]' : '' ) ) . '" value="' . esc_attr( $k ) . '"' . checked( $on, true, false ) . '> ' . esc_html( $lab ) . '</label>';
					}
					echo '</fieldset>';
					break;
				case 'number':
					echo '<input type="number" id="' . esc_attr( $fid ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $v ) . '"' . ( isset( $f['min'] ) ? ' min="' . esc_attr( $f['min'] ) . '"' : '' ) . ( isset( $f['max'] ) ? ' max="' . esc_attr( $f['max'] ) . '"' : '' ) . '>';
					break;
				case 'textarea':
					echo '<textarea id="' . esc_attr( $fid ) . '" name="' . esc_attr( $name ) . '" rows="4">' . esc_textarea( $v ) . '</textarea>';
					break;
				default:
					$type = $f['secret'] ? 'password' : 'text';
					echo '<input type="' . esc_attr( $type ) . '" id="' . esc_attr( $fid ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $v ) . '" autocomplete="off">';
			}
			if ( '' !== $f['desc'] ) {
				echo '<p class="wenpai-field__desc">' . esc_html( $f['desc'] ) . '</p>';
			}
			echo '</div>';
		}
	}
}
