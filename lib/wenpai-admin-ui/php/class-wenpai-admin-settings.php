<?php
/**
 * Declarative settings framework (roadmap layer 3).
 *
 * @package WenpaiAdminUI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Wenpai_Admin_Settings' ) ) {
	/**
	 * One instance per option.
	 *
	 * Field schema keys: type, default, label, desc, choices, rows, row_label,
	 * col_label, unset_value, min, max, secret, sanitize, validate, depends_on.
	 * Types: toggle, select, radio, choice, number, text, textarea, multicheck,
	 * multiselect, matrix, secret, map. See docs/framework/settings.md.
	 */
	class Wenpai_Admin_Settings {

		/**
		 * Submitted list of secret field ids to clear: {option}[__wenpai_clear][].
		 */
		const CLEAR_KEY = '__wenpai_clear';

		/**
		 * Instances keyed by option name.
		 *
		 * @var array<string, Wenpai_Admin_Settings>
		 */
		private static $registry = array();

		/**
		 * Option name in wp_options.
		 *
		 * @var string
		 */
		private $option;

		/**
		 * Settings API option group.
		 *
		 * @var string
		 */
		private $group;

		/**
		 * Field schema keyed by field id, with defaults filled in.
		 *
		 * @var array<string, array>
		 */
		private $fields;

		/**
		 * UI strings. The kit has no text domain; plugins pass translated strings.
		 *
		 * @var array<string, string>
		 */
		private $strings;

		/**
		 * Field errors recorded during sanitize() in this request: id => message.
		 *
		 * @var array<string, string>
		 */
		private $errors = array();

		/**
		 * Create and register a settings instance for one option.
		 *
		 * @param string $option Option name.
		 * @param array  $fields Field schema keyed by field id.
		 * @param array  $args   Optional. Supports 'group' and 'strings'.
		 * @return Wenpai_Admin_Settings
		 */
		public static function register( $option, array $fields, array $args = array() ) {
			$inst                      = new self( $option, $fields, $args );
			self::$registry[ $option ] = $inst;
			if ( function_exists( 'add_action' ) ) {
				add_action( 'admin_init', array( $inst, 'register_setting' ) );
			}
			return $inst;
		}

		/**
		 * Look up a registered instance.
		 *
		 * @param string $option Option name.
		 * @return Wenpai_Admin_Settings|null
		 */
		public static function get_instance( $option ) {
			return isset( self::$registry[ $option ] ) ? self::$registry[ $option ] : null;
		}

		/**
		 * Default UI strings (zh_CN, same wording as the WPSlug and wpsss packs).
		 *
		 * @return array<string, string>
		 */
		public static function default_strings() {
			return array(
				'on'            => '已开启',
				'off'           => '未开启',
				'secret_saved'  => '已保存。留空表示不修改，页面上不会再显示。',
				'secret_empty'  => '还没有保存。',
				'secret_clear'  => '清除已保存的值',
				'saved'         => '已保存。',
				/* translators: %d: number of fields that were not saved. */
				'invalid'       => '暂时无法保存 %d 项设置，请按字段下的提示修改。',
				'invalid_value' => '这一项的值无效，已保留原来的设置。',
			);
		}

		/**
		 * Use register() instead.
		 *
		 * @param string $option Option name.
		 * @param array  $fields Field schema keyed by field id.
		 * @param array  $args   Optional args.
		 */
		private function __construct( $option, array $fields, array $args ) {
			$this->option  = $option;
			$this->group   = isset( $args['group'] ) ? $args['group'] : $option . '_group';
			$this->strings = array_merge(
				self::default_strings(),
				isset( $args['strings'] ) && is_array( $args['strings'] ) ? $args['strings'] : array()
			);
			$this->fields  = array();
			foreach ( $fields as $id => $f ) {
				$f = wp_parse_args(
					$f,
					array(
						'type'        => 'text',
						'default'     => '',
						'label'       => $id,
						'desc'        => '',
						'choices'     => array(),
						'rows'        => array(),
						'row_label'   => '',
						'col_label'   => '',
						'unset_value' => null,
						'secret'      => false,
						'validate'    => null,
						'depends_on'  => array(),
					)
				);
				if ( 'secret' === $f['type'] ) {
					$f['secret'] = true;
				}
				$this->fields[ $id ] = $f;
			}
		}

		/**
		 * Register the option with the Settings API. Hooked on admin_init.
		 */
		public function register_setting() {
			register_setting(
				$this->group,
				$this->option,
				array(
					'type'              => 'array',
					'default'           => array(),
					'show_in_rest'      => false,
					'sanitize_callback' => array( $this, 'sanitize' ),
				)
			);
		}

		/**
		 * Settings API option group.
		 *
		 * @return string
		 */
		public function group() {
			return $this->group;
		}

		/**
		 * Option name.
		 *
		 * @return string
		 */
		public function option() {
			return $this->option;
		}

		/**
		 * Field schema with defaults filled in.
		 *
		 * @return array
		 */
		public function fields() {
			return $this->fields;
		}

		/**
		 * Default value of every field.
		 *
		 * @return array
		 */
		public function defaults() {
			return wp_list_pluck( $this->fields, 'default' );
		}

		/**
		 * Stored option as saved, without defaults.
		 *
		 * @return array
		 */
		private function stored() {
			$stored = get_option( $this->option, array() );
			return is_array( $stored ) ? $stored : array();
		}

		/**
		 * Stored values merged over defaults.
		 *
		 * @return array
		 */
		public function all() {
			return wp_parse_args( $this->stored(), $this->defaults() );
		}

		/**
		 * Value of one field.
		 *
		 * @param string $id Field id.
		 * @return mixed|null Null for an unknown field.
		 */
		public function get( $id ) {
			$all = $this->all();
			return isset( $all[ $id ] ) ? $all[ $id ] : null;
		}

		/**
		 * Whether a field never echoes its value.
		 *
		 * @param array $f Field schema.
		 * @return bool
		 */
		private static function is_secret( array $f ) {
			return ! empty( $f['secret'] );
		}

		/**
		 * Resolve a list that may be given lazily as a Closure (for example post types).
		 *
		 * @param mixed $items Array, or a Closure returning one.
		 * @param array $f    Field schema, passed to the Closure.
		 * @return array
		 */
		private static function resolve( $items, array $f ) {
			if ( $items instanceof Closure ) {
				$items = call_user_func( $items, $f );
			}
			return is_array( $items ) ? $items : array();
		}

		/**
		 * Sanitize callback for the whole option.
		 *
		 * Secret fields: an empty value keeps the saved one; ids listed under
		 * {option}[__wenpai_clear][] are cleared. A 'validate' callback that does
		 * not return true keeps the saved value and records a field error.
		 *
		 * @param mixed $input Raw submitted value.
		 * @return array
		 */
		public function sanitize( $input ) {
			$input  = is_array( $input ) ? $input : array();
			$stored = $this->stored();
			$clear  = isset( $input[ self::CLEAR_KEY ] ) && is_array( $input[ self::CLEAR_KEY ] )
				? array_map( 'strval', $input[ self::CLEAR_KEY ] )
				: array();
			$out    = array();
			foreach ( $this->fields as $id => $f ) {
				$raw  = array_key_exists( $id, $input ) ? $input[ $id ] : null;
				$prev = array_key_exists( $id, $stored ) ? $stored[ $id ] : $f['default'];
				if ( self::is_secret( $f ) ) {
					if ( in_array( (string) $id, $clear, true ) ) {
						$out[ $id ] = '';
						continue;
					}
					if ( null === $raw || ( is_scalar( $raw ) && '' === trim( (string) $raw ) ) ) {
						$out[ $id ] = $prev;
						continue;
					}
				}
				$val = $this->sanitize_field( $f, $raw );
				if ( is_callable( $f['validate'] ) ) {
					$ok = call_user_func( $f['validate'], $val, $f );
					if ( true !== $ok ) {
						$this->add_error( $id, is_string( $ok ) && '' !== $ok ? $ok : $this->strings['invalid_value'] );
						$val = $prev;
					}
				}
				$out[ $id ] = $val;
			}
			return $out;
		}

		/**
		 * Record a field error for this request and for the Settings API redirect.
		 *
		 * @param string $id      Field id.
		 * @param string $message Translated message.
		 */
		private function add_error( $id, $message ) {
			$this->errors[ $id ] = $message;
			if ( function_exists( 'add_settings_error' ) ) {
				add_settings_error( $this->option, (string) $id, $message, 'error' );
			}
		}

		/**
		 * Field errors from this request or from the last options.php redirect.
		 *
		 * @return array<string, string> Field id => message.
		 */
		public function errors() {
			$errs = $this->errors;
			if ( function_exists( 'get_settings_errors' ) ) {
				foreach ( (array) get_settings_errors( $this->option ) as $e ) {
					if ( isset( $e['code'], $e['message'] ) && isset( $this->fields[ $e['code'] ] ) && ! isset( $errs[ $e['code'] ] ) ) {
						$errs[ $e['code'] ] = $e['message'];
					}
				}
			}
			return $errs;
		}

		/**
		 * Sanitize one field value by its type.
		 *
		 * @param array $f Field schema.
		 * @param mixed $v Raw value.
		 * @return mixed
		 */
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
				case 'choice':
					$v = is_scalar( $v ) ? (string) $v : '';
					return array_key_exists( $v, self::resolve( $f['choices'], $f ) ) ? $v : $f['default'];
				case 'multicheck':
					$v = is_array( $v ) ? array_values( array_filter( array_map( 'sanitize_key', $v ), 'strlen' ) ) : array();
					return empty( $f['choices'] ) ? $v : array_values( array_intersect( $v, array_keys( self::resolve( $f['choices'], $f ) ) ) );
				case 'multiselect':
					return self::sanitize_multiselect( $f, $v );
				case 'matrix':
					return self::sanitize_matrix( $f, $v );
				case 'map':
					$res = array();
					foreach ( ( is_array( $v ) ? $v : array() ) as $k => $val ) {
						$res[ sanitize_key( $k ) ] = sanitize_key( $val );
					}
					return $res;
				case 'textarea':
					return null === $v ? $f['default'] : sanitize_textarea_field( wp_unslash( $v ) );
				default:
					return null === $v || ! is_scalar( $v ) ? $f['default'] : sanitize_text_field( wp_unslash( (string) $v ) );
			}
		}

		/**
		 * Multiselect: list of choice keys, in choice order, no duplicates.
		 *
		 * @param array $f Field schema.
		 * @param mixed $v Raw value. The empty sentinel from the form is dropped.
		 * @return string[]
		 */
		private static function sanitize_multiselect( array $f, $v ) {
			$picked = array();
			foreach ( ( is_array( $v ) ? $v : array() ) as $item ) {
				if ( is_scalar( $item ) && '' !== (string) $item ) {
					$picked[] = sanitize_text_field( (string) $item );
				}
			}
			$choices = self::resolve( $f['choices'], $f );
			if ( empty( $choices ) ) {
				return array_values( array_unique( $picked ) );
			}
			$out = array();
			foreach ( array_keys( $choices ) as $k ) {
				if ( in_array( (string) $k, $picked, true ) ) {
					$out[] = (string) $k;
				}
			}
			return $out;
		}

		/**
		 * Matrix: row key => choice key. Unknown rows and choices are dropped;
		 * rows set to unset_value are not stored (they follow the global setting).
		 *
		 * @param array $f Field schema.
		 * @param mixed $v Raw value.
		 * @return array<string, string>
		 */
		private static function sanitize_matrix( array $f, $v ) {
			$rows    = self::resolve( $f['rows'], $f );
			$choices = self::resolve( $f['choices'], $f );
			$out     = array();
			foreach ( ( is_array( $v ) ? $v : array() ) as $row => $val ) {
				$row = sanitize_key( (string) $row );
				$val = is_scalar( $val ) ? (string) $val : '';
				if ( '' === $row || ( ! empty( $rows ) && ! array_key_exists( $row, $rows ) ) ) {
					continue;
				}
				if ( ! array_key_exists( $val, $choices ) ) {
					continue;
				}
				if ( null !== $f['unset_value'] && (string) $f['unset_value'] === $val ) {
					continue;
				}
				$out[ $row ] = $val;
			}
			return $out;
		}

		/**
		 * Values for export, with secret fields removed.
		 *
		 * @return array
		 */
		public function export() {
			$data = $this->all();
			foreach ( $this->fields as $id => $f ) {
				if ( self::is_secret( $f ) ) {
					unset( $data[ $id ] );
				}
			}
			return $data;
		}

		/**
		 * Import values; secret fields keep their current value.
		 *
		 * @param array $data Values to import.
		 * @return bool Result of update_option().
		 */
		public function import( array $data ) {
			$current = $this->all();
			foreach ( $this->fields as $id => $f ) {
				if ( self::is_secret( $f ) ) {
					unset( $data[ $id ] );
				}
			}
			$clean = $this->sanitize( wp_parse_args( $data, $current ) );
			foreach ( $this->fields as $id => $f ) {
				if ( self::is_secret( $f ) ) {
					$clean[ $id ] = $current[ $id ];
				}
			}
			return update_option( $this->option, $clean );
		}

		/**
		 * Echo the markup for one field.
		 *
		 * @param string $id Field id.
		 */
		public function render_field( $id ) {
			echo $this->field_html( $id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- field_html() escapes every part.
		}

		/**
		 * Echo only the control of one field, for layouts that draw their own
		 * label (simple-row). Pass 'labelledby' with the id of that label.
		 *
		 * @param string $id   Field id.
		 * @param array  $args Optional. 'labelledby', 'describedby'.
		 */
		public function render_control( $id, array $args = array() ) {
			if ( ! isset( $this->fields[ $id ] ) ) {
				return;
			}
			$f   = $this->fields[ $id ];
			$fid = $this->field_id( $id );
			$ids = array();
			if ( ! empty( $args['describedby'] ) ) {
				$ids[] = (string) $args['describedby'];
			}
			$ctl = array(
				'labelledby'  => isset( $args['labelledby'] ) ? (string) $args['labelledby'] : '',
				'describedby' => implode( ' ', $ids ),
				'invalid'     => isset( $this->errors()[ $id ] ),
			);
			$out = '<span class="wenpai-control" data-wenpai-field="' . esc_attr( $id ) . '"' . $this->depends_attrs( $f ) . '>' . $this->control_html( $id, $f, $fid, $ctl ) . '</span>';
			echo $out; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
		}

		/**
		 * HTML id of a field's control.
		 *
		 * @param string $id Field id.
		 * @return string
		 */
		public function field_id( $id ) {
			return 'wenpai-f-' . $this->option . '-' . $id;
		}

		/**
		 * Form input name of a field.
		 *
		 * @param string $id Field id.
		 * @return string
		 */
		public function field_name( $id ) {
			return $this->option . '[' . $id . ']';
		}

		/**
		 * Markup for one field: wrapper, label (or legend), control, description, error.
		 *
		 * @param string $id Field id.
		 * @return string Escaped HTML, or '' for an unknown field.
		 */
		public function field_html( $id ) {
			if ( ! isset( $this->fields[ $id ] ) ) {
				return '';
			}
			$f      = $this->fields[ $id ];
			$fid    = $this->field_id( $id );
			$errors = $this->errors();
			$err    = isset( $errors[ $id ] ) ? $errors[ $id ] : '';
			$group  = in_array( $f['type'], array( 'radio', 'choice', 'multicheck', 'multiselect', 'matrix' ), true );
			$secret = self::is_secret( $f ) && 'toggle' !== $f['type'];

			$described = array();
			if ( '' !== $f['desc'] ) {
				$described[] = $fid . '-desc';
			}
			if ( $secret ) {
				$described[] = $fid . '-state';
			}
			if ( '' !== $err ) {
				$described[] = $fid . '-error';
			}
			$ctl = array(
				'labelledby'  => '',
				'describedby' => $group ? '' : implode( ' ', $described ),
				'invalid'     => '' !== $err,
			);

			$cls  = 'wenpai-field wenpai-field--' . $f['type'] . ( '' !== $err ? ' is-invalid' : '' );
			$html = '<div class="' . esc_attr( $cls ) . '" data-wenpai-field="' . esc_attr( $id ) . '"' . $this->depends_attrs( $f ) . '>';
			if ( $group ) {
				$aria  = empty( $described ) ? '' : ' aria-describedby="' . esc_attr( implode( ' ', $described ) ) . '"';
				$html .= '<fieldset class="wenpai-field__group" id="' . esc_attr( $fid ) . '"' . $aria . '>';
				$html .= '<legend class="wenpai-field__label">' . esc_html( $f['label'] ) . '</legend>';
				$html .= $this->control_html( $id, $f, $fid, $ctl );
				$html .= '</fieldset>';
			} else {
				$html .= '<label class="wenpai-field__label" for="' . esc_attr( $fid ) . '">' . esc_html( $f['label'] ) . '</label>';
				$html .= $this->control_html( $id, $f, $fid, $ctl );
			}
			if ( '' !== $f['desc'] ) {
				$html .= '<p class="wenpai-field__desc" id="' . esc_attr( $fid . '-desc' ) . '">' . esc_html( $f['desc'] ) . '</p>';
			}
			if ( $secret ) {
				$html .= $this->secret_extra_html( $id, $fid );
			}
			if ( '' !== $err ) {
				$html .= '<p class="wenpai-field__error" id="' . esc_attr( $fid . '-error' ) . '">' . esc_html( $err ) . '</p>';
			}
			return $html . '</div>';
		}

		/**
		 * Secret field state line and the clear checkbox.
		 *
		 * @param string $id  Field id.
		 * @param string $fid Control id.
		 * @return string
		 */
		private function secret_extra_html( $id, $fid ) {
			$stored = $this->stored();
			$saved  = isset( $stored[ $id ] ) && is_scalar( $stored[ $id ] ) && '' !== (string) $stored[ $id ];
			$html   = '<p class="wenpai-field__state" id="' . esc_attr( $fid . '-state' ) . '">' . esc_html( $saved ? $this->strings['secret_saved'] : $this->strings['secret_empty'] ) . '</p>';
			if ( $saved ) {
				$html .= '<label class="wenpai-field__clear"><input type="checkbox" name="' . esc_attr( $this->option . '[' . self::CLEAR_KEY . '][]' ) . '" value="' . esc_attr( $id ) . '"> ' . esc_html( $this->strings['secret_clear'] ) . '</label>';
			}
			return $html;
		}

		/**
		 * Control markup by type.
		 *
		 * @param string $id  Field id.
		 * @param array  $f   Field schema.
		 * @param string $fid Control id.
		 * @param array  $ctl 'labelledby', 'describedby', 'invalid'.
		 * @return string
		 */
		private function control_html( $id, array $f, $fid, array $ctl ) {
			$v    = $this->get( $id );
			$name = $this->field_name( $id );
			$aria = '';
			if ( '' !== $ctl['labelledby'] ) {
				$aria .= ' aria-labelledby="' . esc_attr( $ctl['labelledby'] ) . '"';
			}
			if ( '' !== $ctl['describedby'] ) {
				$aria .= ' aria-describedby="' . esc_attr( $ctl['describedby'] ) . '"';
			}
			$inv = $ctl['invalid'] ? ' aria-invalid="true"' : '';
			$out = '';
			switch ( $f['type'] ) {
				case 'toggle':
					return self::switch_html(
						array(
							'id'          => $fid,
							'name'        => $name,
							'checked'     => (bool) $v,
							'on'          => $this->strings['on'],
							'off'         => $this->strings['off'],
							'labelledby'  => $ctl['labelledby'],
							'describedby' => $ctl['describedby'],
							'invalid'     => $ctl['invalid'],
						)
					);
				case 'select':
					$out .= '<select id="' . esc_attr( $fid ) . '" name="' . esc_attr( $name ) . '"' . $aria . $inv . '>';
					foreach ( self::resolve( $f['choices'], $f ) as $k => $lab ) {
						$out .= '<option value="' . esc_attr( $k ) . '"' . selected( (string) $v, (string) $k, false ) . '>' . esc_html( self::choice_label( $lab ) ) . '</option>';
					}
					return $out . '</select>';
				case 'radio':
				case 'multicheck':
					$multi = 'multicheck' === $f['type'];
					$out  .= '<div class="' . ( $multi ? 'chk' : 'opts' ) . '">';
					foreach ( self::resolve( $f['choices'], $f ) as $k => $lab ) {
						$on   = $multi ? in_array( (string) $k, array_map( 'strval', (array) $v ), true ) : ( (string) $v === (string) $k );
						$out .= '<label><input type="' . ( $multi ? 'checkbox' : 'radio' ) . '" name="' . esc_attr( $name . ( $multi ? '[]' : '' ) ) . '" value="' . esc_attr( $k ) . '"' . checked( $on, true, false ) . $inv . '> ' . esc_html( self::choice_label( $lab ) ) . '</label>';
					}
					return $out . '</div>';
				case 'choice':
					$out .= '<div class="choice-cards' . ( 2 === count( self::resolve( $f['choices'], $f ) ) ? ' cols-2' : '' ) . '">';
					foreach ( self::resolve( $f['choices'], $f ) as $k => $c ) {
						$on   = (string) $v === (string) $k;
						$desc = is_array( $c ) && isset( $c['desc'] ) ? (string) $c['desc'] : '';
						$dis  = is_array( $c ) && ! empty( $c['disabled'] );
						$out .= '<label class="choice' . ( $on ? ' is-on' : '' ) . ( $dis ? ' is-soon' : '' ) . '">';
						$out .= '<input type="radio" name="' . esc_attr( $name ) . '" value="' . esc_attr( $k ) . '"' . checked( $on, true, false ) . disabled( $dis, true, false ) . $inv . '>';
						$out .= '<span><span class="t">' . esc_html( self::choice_label( $c ) ) . '</span>' . ( '' !== $desc ? '<span class="d">' . esc_html( $desc ) . '</span>' : '' ) . '</span></label>';
					}
					return $out . '</div>';
				case 'multiselect':
					$picked = array_map( 'strval', (array) $v );
					$out   .= '<div class="chk-grid">';
					$out   .= '<input type="hidden" name="' . esc_attr( $name . '[]' ) . '" value="">';
					foreach ( self::resolve( $f['choices'], $f ) as $k => $lab ) {
						$out .= '<label><input type="checkbox" name="' . esc_attr( $name . '[]' ) . '" value="' . esc_attr( $k ) . '"' . checked( in_array( (string) $k, $picked, true ), true, false ) . $inv . '> ' . esc_html( self::choice_label( $lab ) ) . '</label>';
					}
					return $out . '</div>';
				case 'matrix':
					return $this->matrix_html( $f, $fid, $name, is_array( $v ) ? $v : array(), $inv );
				case 'number':
					return '<input type="number" id="' . esc_attr( $fid ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $v ) . '"' . ( isset( $f['min'] ) ? ' min="' . esc_attr( $f['min'] ) . '"' : '' ) . ( isset( $f['max'] ) ? ' max="' . esc_attr( $f['max'] ) . '"' : '' ) . $aria . $inv . '>';
				case 'textarea':
					return '<textarea id="' . esc_attr( $fid ) . '" name="' . esc_attr( $name ) . '" rows="4"' . $aria . $inv . '>' . esc_textarea( $v ) . '</textarea>';
				default:
					if ( self::is_secret( $f ) ) {
						return '<input type="password" id="' . esc_attr( $fid ) . '" name="' . esc_attr( $name ) . '" value="" autocomplete="new-password" spellcheck="false"' . $aria . $inv . '>';
					}
					return '<input type="text" id="' . esc_attr( $fid ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( is_scalar( $v ) ? $v : '' ) . '" autocomplete="off"' . $aria . $inv . '>';
			}
		}

		/**
		 * Matrix table: one select per row.
		 *
		 * @param array  $f    Field schema.
		 * @param string $fid  Control id.
		 * @param string $name Input name.
		 * @param array  $v    Current map.
		 * @param string $inv  aria-invalid attribute or ''.
		 * @return string
		 */
		private function matrix_html( array $f, $fid, $name, array $v, $inv ) {
			$choices = self::resolve( $f['choices'], $f );
			$fall    = null !== $f['unset_value'] ? (string) $f['unset_value'] : (string) key( $choices );
			$col     = $fid . '-col';
			$out     = '<table class="tbl wenpai-matrix"><thead><tr>';
			$out    .= '<th scope="col">' . esc_html( $f['row_label'] ) . '</th>';
			$out    .= '<th scope="col" id="' . esc_attr( $col ) . '">' . esc_html( $f['col_label'] ) . '</th>';
			$out    .= '</tr></thead><tbody>';
			foreach ( self::resolve( $f['rows'], $f ) as $row => $r ) {
				$rid  = $fid . '-r-' . sanitize_key( (string) $row );
				$cur  = isset( $v[ $row ] ) && array_key_exists( (string) $v[ $row ], $choices ) ? (string) $v[ $row ] : $fall;
				$desc = is_array( $r ) && isset( $r['desc'] ) ? (string) $r['desc'] : '';
				$out .= '<tr><th scope="row" id="' . esc_attr( $rid ) . '"><span class="t">' . esc_html( self::choice_label( $r ) ) . '</span>' . ( '' !== $desc ? '<span class="d">' . esc_html( $desc ) . '</span>' : '' ) . '</th>';
				$out .= '<td><select name="' . esc_attr( $name . '[' . $row . ']' ) . '" aria-labelledby="' . esc_attr( $rid . ' ' . $col ) . '"' . $inv . '>';
				foreach ( $choices as $k => $lab ) {
					$out .= '<option value="' . esc_attr( $k ) . '"' . selected( $cur, (string) $k, false ) . '>' . esc_html( self::choice_label( $lab ) ) . '</option>';
				}
				$out .= '</select></td></tr>';
			}
			return $out . '</tbody></table>';
		}

		/**
		 * Label of a choice or row given as a string or array('label' => ...).
		 *
		 * @param mixed $c Choice.
		 * @return string
		 */
		private static function choice_label( $c ) {
			if ( is_array( $c ) ) {
				return isset( $c['label'] ) ? (string) $c['label'] : '';
			}
			return (string) $c;
		}

		/**
		 * The one kit switch: a native checkbox with role="switch", styled as a
		 * track. Works without JS. A hidden "0" input makes "off" submit.
		 *
		 * @param array $a {
		 *     Switch arguments.
		 *     @type string $id          Control id. Required.
		 *     @type string $name        Input name. Omit for a display-only switch.
		 *     @type bool   $checked     Current state.
		 *     @type string $on          State text when on (translated).
		 *     @type string $off         State text when off (translated).
		 *     @type bool   $disabled    Disabled.
		 *     @type string $labelledby  Id of the visible label, when not using <label for>.
		 *     @type string $describedby Space-separated ids.
		 *     @type bool   $invalid     Adds aria-invalid.
		 * }
		 * @return string
		 */
		public static function switch_html( array $a ) {
			$a    = wp_parse_args(
				$a,
				array(
					'id'          => '',
					'name'        => '',
					'checked'     => false,
					'on'          => '',
					'off'         => '',
					'disabled'    => false,
					'labelledby'  => '',
					'describedby' => '',
					'invalid'     => false,
				)
			);
			$attr = ' id="' . esc_attr( $a['id'] ) . '"';
			if ( '' !== $a['name'] ) {
				$attr .= ' name="' . esc_attr( $a['name'] ) . '" value="1"';
			}
			if ( '' !== $a['labelledby'] ) {
				$attr .= ' aria-labelledby="' . esc_attr( $a['labelledby'] ) . '"';
			}
			if ( '' !== $a['describedby'] ) {
				$attr .= ' aria-describedby="' . esc_attr( $a['describedby'] ) . '"';
			}
			if ( $a['invalid'] ) {
				$attr .= ' aria-invalid="true"';
			}
			$out = '<span class="wenpai-switch">';
			if ( '' !== $a['name'] ) {
				$out .= '<input type="hidden" name="' . esc_attr( $a['name'] ) . '" value="0">';
			}
			$out .= '<input type="checkbox" role="switch" class="wenpai-switch__input"' . $attr . checked( (bool) $a['checked'], true, false ) . disabled( (bool) $a['disabled'], true, false ) . '>';
			$out .= '<span class="wenpai-switch__track" aria-hidden="true"></span>';
			if ( '' !== $a['on'] || '' !== $a['off'] ) {
				$out .= '<span class="wenpai-switch__state" aria-hidden="true"><span class="wenpai-switch__on">' . esc_html( $a['on'] ) . '</span><span class="wenpai-switch__off">' . esc_html( $a['off'] ) . '</span></span>';
			}
			return $out . '</span>';
		}

		/**
		 * Normalize depends_on into a list of conditions.
		 *
		 * @param array $f Field schema.
		 * @return array<int, array{field: string, value: string[], not: bool}>
		 */
		private static function conditions( array $f ) {
			$dep = $f['depends_on'];
			if ( empty( $dep ) || ! is_array( $dep ) ) {
				return array();
			}
			if ( isset( $dep['field'] ) ) {
				$dep = array( $dep );
			}
			$out = array();
			foreach ( $dep as $c ) {
				if ( ! is_array( $c ) || empty( $c['field'] ) ) {
					continue;
				}
				$vals = array_key_exists( 'value', $c ) ? $c['value'] : true;
				$vals = is_array( $vals ) ? $vals : array( $vals );
				$norm = array();
				foreach ( $vals as $x ) {
					if ( is_bool( $x ) ) {
						$norm[] = $x ? '1' : '0';
					} else {
						$norm[] = (string) $x;
					}
				}
				$out[] = array(
					'field' => (string) $c['field'],
					'value' => $norm,
					'not'   => ! empty( $c['not'] ),
				);
			}
			return $out;
		}

		/**
		 * Data attributes for kit JS conditional display. No `hidden` is printed:
		 * without JS every field shows.
		 *
		 * One condition: data-wenpai-depends-on, data-wenpai-depends-value (a value,
		 * or a JSON list for "any of"), data-wenpai-depends-not. Several conditions
		 * (all must hold): data-wenpai-depends, a JSON list of {on, value, not}.
		 *
		 * @param array $f Field schema.
		 * @return string
		 */
		private function depends_attrs( array $f ) {
			$conds = self::conditions( $f );
			if ( empty( $conds ) ) {
				return '';
			}
			if ( 1 === count( $conds ) ) {
				$c   = $conds[0];
				$val = 1 === count( $c['value'] ) ? $c['value'][0] : wp_json_encode( $c['value'] );
				return ' data-wenpai-depends-on="' . esc_attr( $this->field_name( $c['field'] ) ) . '" data-wenpai-depends-value="' . esc_attr( $val ) . '"' . ( $c['not'] ? ' data-wenpai-depends-not' : '' );
			}
			$list = array();
			foreach ( $conds as $c ) {
				$list[] = array(
					'on'    => $this->field_name( $c['field'] ),
					'value' => $c['value'],
					'not'   => $c['not'],
				);
			}
			return ' data-wenpai-depends="' . esc_attr( wp_json_encode( $list ) ) . '"';
		}

		/**
		 * Attributes for a hand-written block that should follow a field, for
		 * example a preview card that shows only while conversion is on.
		 *
		 * @param array $depends_on Same shape as the schema key.
		 * @return string Leading space included.
		 */
		public function depends_on_attrs( array $depends_on ) {
			return $this->depends_attrs( array( 'depends_on' => $depends_on ) );
		}

		/**
		 * Kit notice for the settings page: an error summary (role="alert") when
		 * fields failed validation, otherwise a saved receipt (role="status").
		 *
		 * @param bool|null $updated Whether the form was just saved. Null reads
		 *                           settings-updated from the options.php redirect.
		 * @return string
		 */
		public function notices_html( $updated = null ) {
			if ( null === $updated ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only flag set by options.php redirect.
				$updated = isset( $_GET['settings-updated'] ) && 'true' === sanitize_text_field( wp_unslash( $_GET['settings-updated'] ) );
			}
			$errs = $this->errors();
			$icon = class_exists( 'Wenpai_Admin_Icons', false ) ? Wenpai_Admin_Icons::svg( 'info' ) : '';
			if ( ! empty( $errs ) ) {
				return '<div class="wenpai-notice-wrap"><div class="notice bad" role="alert">' . $icon . '<span>' . esc_html( sprintf( $this->strings['invalid'], count( $errs ) ) ) . '</span></div></div>';
			}
			if ( $updated ) {
				return '<div class="wenpai-notice-wrap" data-wenpai-fade><div class="notice ok" role="status" data-wenpai-announce>' . $icon . '<span>' . esc_html( $this->strings['saved'] ) . '</span></div></div>';
			}
			return '';
		}
	}
}
