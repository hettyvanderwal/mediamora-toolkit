<?php
/**
 * Nagebootste klassen van Elementor Pro voor tests/formuliertest.php. Alleen
 * wat de formuliertest gebruikt, en zo dicht mogelijk bij Elementor Pro 4.3.3
 * (modules/forms/classes/ajax-handler.php, form-record.php en
 * actions/email.php). Staat in een submap, zodat de workflow het niet als
 * los testbestand draait.
 *
 * Elke actie houdt in $GLOBALS['nep_gedraaid'] bij dat hij is gedraaid.
 */

namespace ElementorPro {

	class Plugin {
		public static function elementor() {
			return $GLOBALS['nep_elementor'];
		}
	}
}

namespace ElementorPro\Modules\Forms {

	class Module {
		public $actions_registrar;
		private static $instance;

		public static function instance() {
			if ( ! self::$instance ) {
				self::$instance                    = new self();
				self::$instance->actions_registrar = new Nep_Registrar();
			}
			return self::$instance;
		}

		public static function find_element_recursive( $elements, $form_id ) {
			foreach ( $elements as $element ) {
				if ( $form_id === $element['id'] ) {
					return $element;
				}
				if ( ! empty( $element['elements'] ) ) {
					$element = self::find_element_recursive( $element['elements'], $form_id );
					if ( $element ) {
						return $element;
					}
				}
			}
			return false;
		}
	}

	class Nep_Registrar {
		public $items = array();

		public function __construct() {
			foreach ( array( 'email', 'email2', 'webhook', 'redirect', 'mailerlite', 'activecampaign', 'save-to-database', 'popup' ) as $naam ) {
				if ( 'email' === $naam ) {
					$this->items[ $naam ] = new Actions\Email();
				} elseif ( 'email2' === $naam ) {
					$this->items[ $naam ] = new Actions\Email2();
				} else {
					$this->items[ $naam ] = new Actions\Andere( $naam );
				}
			}
		}

		public function get( $id = null ) {
			if ( ! $id ) {
				return $this->items;
			}
			return isset( $this->items[ $id ] ) ? $this->items[ $id ] : null;
		}
	}
}

namespace ElementorPro\Modules\Forms\Classes {

	class Ajax_Handler {
		public $is_success = true;
		public $messages   = array(
			'success'     => array(),
			'error'       => array(),
			'admin_error' => array(),
		);
		public $data       = array();
		public $errors     = array();

		const SERVER_ERROR   = 'server_error';
		const FIELD_REQUIRED = 'required_field';

		public function __construct() {
			// Zoals Elementor Pro: de ajax-acties. De test mag dit niet aanroepen.
			$GLOBALS['nep_handler_constructor'] = true;
		}

		public static function get_default_message( $id, $settings ) {
			return self::FIELD_REQUIRED === $id ? 'This field is required.' : 'Your submission failed because of a server error.';
		}

		public function add_error( $field, $message = '' ) {
			if ( is_array( $field ) ) {
				$this->errors += $field;
			} else {
				$this->errors[ $field ] = $message;
			}
			$this->is_success = false;
			return $this;
		}

		public function add_error_message( $message ) {
			$this->messages['error'][] = $message;
			$this->is_success          = false;
			return $this;
		}

		public function send() {
			$GLOBALS['nep_send'] = true;
		}
	}

	class Form_Record {
		protected $sent_data;
		protected $fields = array();
		protected $form_settings;
		protected $meta   = array();
		protected $files  = array();

		public function __construct( $sent_data, $form ) {
			$this->form_settings = $form['settings'];
			$this->sent_data     = $sent_data;
			foreach ( $this->form_settings['form_fields'] as $form_field ) {
				$field = array(
					'id'        => $form_field['custom_id'],
					'type'      => $form_field['field_type'],
					'title'     => $form_field['field_label'],
					'value'     => '',
					'raw_value' => '',
					'required'  => ! empty( $form_field['required'] ),
				);
				if ( isset( $this->sent_data[ $form_field['custom_id'] ] ) ) {
					$field['raw_value'] = $this->sent_data[ $form_field['custom_id'] ];
					$value              = $field['raw_value'];
					$field['value']     = is_array( $value ) ? implode( ', ', $value ) : $value;
				}
				$this->fields[ $form_field['custom_id'] ] = $field;
			}
			$this->meta = array(
				'page_url' => array(
					'title' => 'Page URL',
					'value' => isset( $_POST['referrer'] ) ? $_POST['referrer'] : '',
				),
			);
		}

		public function validate( $ajax_handler ) {
			foreach ( $this->fields as $id => $field ) {
				if ( ! empty( $field['required'] ) && '' === $field['value'] && 'upload' !== $field['type'] ) {
					$ajax_handler->add_error( $id, Ajax_Handler::get_default_message( Ajax_Handler::FIELD_REQUIRED, $this->form_settings ) );
				}
				do_action( "elementor_pro/forms/validation/{$field['type']}", $field, $this, $ajax_handler );
			}
			do_action( 'elementor_pro/forms/validation', $this, $ajax_handler );
			return empty( $ajax_handler->errors );
		}

		public function process_fields( $ajax_handler ) {
			foreach ( $this->fields as $field ) {
				do_action( "elementor_pro/forms/process/{$field['type']}", $field, $this, $ajax_handler );
			}
			do_action( 'elementor_pro/forms/process', $this, $ajax_handler );
		}

		public function get( $property ) {
			return isset( $this->{$property} ) ? $this->{$property} : null;
		}

		public function get_form_settings( $setting ) {
			return isset( $this->form_settings[ $setting ] ) ? $this->form_settings[ $setting ] : null;
		}

		public function get_field( $args ) {
			return array_filter(
				$this->fields,
				function ( $field ) use ( $args ) {
					return $field['type'] === $args['type'];
				}
			);
		}

		public function remove_field( $id ) {
			unset( $this->fields[ $id ] );
		}

		public function replace_setting_shortcodes( $setting ) {
			return preg_replace_callback(
				'/(\[field[^]]*id="(\w+)"[^]]*\])/',
				function ( $matches ) {
					return isset( $this->fields[ $matches[2] ] ) ? $this->fields[ $matches[2] ]['value'] : '';
				},
				$setting
			);
		}
	}
}

namespace ElementorPro\Modules\Forms\Actions {

	class Email {
		public function get_name() {
			return 'email';
		}

		protected function get_control_id( $control_id ) {
			return $control_id;
		}

		public function run( $record, $ajax_handler ) {
			$GLOBALS['nep_gedraaid'][] = $this->get_name();
			$settings                  = $record->get( 'form_settings' );

			if ( ! empty( $settings['nep_exception'] ) ) {
				throw new \Exception( 'Kon de mail niet opbouwen voor info@klant.nl' );
			}

			$fields = array(
				'email_to'      => 'admin@voorbeeld.nl',
				'email_subject' => 'New message from "Voorbeeld"',
				'email_content' => '[all-fields]',
				'email_to_cc'   => '',
				'email_to_bcc'  => '',
			);
			foreach ( $fields as $key => $default ) {
				$setting = isset( $settings[ $this->get_control_id( $key ) ] ) ? trim( $settings[ $this->get_control_id( $key ) ] ) : '';
				$setting = $record->replace_setting_shortcodes( $setting );
				if ( ! empty( $setting ) ) {
					$fields[ $key ] = $setting;
				}
			}

			$text = '';
			foreach ( $record->get( 'fields' ) as $field ) {
				$text .= $field['title'] . ': ' . $field['value'] . "\n";
			}
			$fields['email_content'] = str_replace( '[all-fields]', $text, $fields['email_content'] );

			$headers = "From: Voorbeeld <admin@voorbeeld.nl>\r\n";
			$cc      = '' !== $fields['email_to_cc'] ? 'Cc: ' . $fields['email_to_cc'] . "\r\n" : '';

			$sent = wp_mail( $fields['email_to'], $fields['email_subject'], $fields['email_content'], $headers . $cc );

			if ( ! empty( $fields['email_to_bcc'] ) ) {
				foreach ( explode( ',', $fields['email_to_bcc'] ) as $bcc ) {
					wp_mail( trim( $bcc ), $fields['email_subject'], $fields['email_content'], $headers );
				}
			}

			if ( ! $sent ) {
				$message = \ElementorPro\Modules\Forms\Classes\Ajax_Handler::get_default_message( 'server_error', $settings );
				$ajax_handler->add_error_message( $message );
				throw new \Exception( $message );
			}
		}
	}

	class Email2 extends Email {
		public function get_name() {
			return 'email2';
		}

		protected function get_control_id( $control_id ) {
			return $control_id . '_2';
		}
	}

	class Andere {
		private $naam;

		public function __construct( $naam ) {
			$this->naam = $naam;
		}

		public function get_name() {
			return $this->naam;
		}

		public function run( $record, $ajax_handler ) {
			$GLOBALS['nep_gedraaid'][] = $this->naam;
		}
	}
}

namespace {

	/**
	 * De documenten, het widget en de database-klasse van Elementor zelf.
	 */
	class Nep_Elementor {
		public $documents;
		public $elements_manager;
		public $db;

		public function __construct() {
			$this->documents        = new Nep_Documenten();
			$this->elements_manager = new Nep_Elementen();
			$this->db               = new Nep_Db();
		}
	}

	class Nep_Documenten {
		/** @var array<int, array> post-ID => elementen */
		public $data = array();

		public function get( $id ) {
			return isset( $this->data[ (int) $id ] ) ? new Nep_Document( (int) $id, $this->data[ (int) $id ] ) : false;
		}
	}

	class Nep_Document {
		private $id;
		private $elementen;

		public function __construct( $id, $elementen ) {
			$this->id        = $id;
			$this->elementen = $elementen;
		}

		public function get_id() {
			return $this->id;
		}

		public function get_elements_data() {
			return $this->elementen;
		}
	}

	class Nep_Elementen {
		public function create_element_instance( $data ) {
			return isset( $data['widgetType'] ) && 'form' === $data['widgetType'] ? new Nep_Widget( $data['settings'] ) : null;
		}
	}

	class Nep_Widget {
		private $settings;

		public function __construct( $settings ) {
			$this->settings = $settings;
		}

		// De standaard van Elementor Pro voor submit_actions is array( 'email' ).
		public function get_settings_for_display() {
			if ( ! empty( $this->settings['nep_laadfout'] ) ) {
				throw new \RuntimeException( 'Widget kan niet laden' );
			}
			return array_merge(
				array(
					'form_name'      => 'New Form',
					'submit_actions' => array( 'email' ),
				),
				$this->settings
			);
		}
	}

	class Nep_Db {
		public $huidig = array();

		public function switch_to_post( $id ) {
			$this->huidig[] = $id;
		}

		public function restore_current_post() {
			array_pop( $this->huidig );
		}
	}
}
