<?php

namespace Elementor\Core\Experiments;

use Elementor\Core\Base\Base_Object;
use Elementor\Core\Upgrade\Manager as Upgrade_Manager;
use Elementor\Plugin;
use Elementor\Settings;
use Elementor\Tracker;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Manager extends Base_Object {

	const RELEASE_STATUS_DEV = 'dev';

	const RELEASE_STATUS_ALPHA = 'alpha';

	const RELEASE_STATUS_BETA = 'beta';

	const RELEASE_STATUS_RC = 'rc';

	const RELEASE_STATUS_STABLE = 'stable';

	const STATE_DEFAULT = 'default';

	const STATE_ACTIVE = 'active';

	const STATE_INACTIVE = 'inactive';

	private $states;

	private $release_statuses;

	private $features;

	/**
	 * Add Feature
	 *
	 * @since 3.1.0
	 * @access public
	 *
	 * @param array $options {
	 *     @type string $name
	 *     @type string $title
	 *     @type string $description
	 *     @type string $release_status
	 *     @type string $default
	 *     @type callable $on_state_change
	 * }
	 *
	 * @return array|null
	 */
	public function add_feature( array $options ) {
		if ( isset( $this->features[ $options['name'] ] ) ) {
			return null;
		}

		$default_experimental_data = [
			'description' => '',
			'release_status' => self::RELEASE_STATUS_ALPHA,
			'default' => self::STATE_INACTIVE,
			'new_site' => [
				'default_active' => false,
				'always_active' => false,
				'minimum_installation_version' => null,
			],
			'on_state_change' => null,
		];

		$allowed_options = [ 'name', 'title', 'description', 'release_status', 'default', 'new_site', 'on_state_change' ];

		$experimental_data = $this->merge_properties( $default_experimental_data, $options, $allowed_options );

		$new_site = $experimental_data['new_site'];

		$feature_is_mutable = true;

		if ( $new_site['default_active'] || $new_site['always_active'] ) {
			$is_new_installation = Upgrade_Manager::install_compare( $new_site['minimum_installation_version'], '>=' );

			if ( $is_new_installation ) {
				if ( $new_site['always_active'] ) {
					$experimental_data['state'] = self::STATE_ACTIVE;

					$feature_is_mutable = false;
				} elseif ( $new_site['default_active'] ) {
					$experimental_data['default'] = self::STATE_ACTIVE;
				}
			}
		}

		$experimental_data['mutable'] = $feature_is_mutable;

		if ( $feature_is_mutable ) {
			$state = $this->get_saved_feature_state( $options['name'] );

			if ( ! $state ) {
				$state = self::STATE_DEFAULT;
			}

			$experimental_data['state'] = $state;
		}

		$this->features[ $options['name'] ] = $experimental_data;

		if ( $feature_is_mutable && is_admin() ) {
			$feature_option_key = $this->get_feature_option_key( $options['name'] );

			$on_state_change_callback = function( $old_state, $new_state ) use ( $experimental_data ) {
				$this->on_feature_state_change( $experimental_data, $new_state );
			};

			add_action( 'add_option_' . $feature_option_key, $on_state_change_callback, 10, 2 );
			add_action( 'update_option_' . $feature_option_key, $on_state_change_callback, 10, 2 );
		}

		do_action( 'elementor/experiments/feature-registered', $this, $experimental_data );

		return $experimental_data;
	}

	/**
	 * Remove Feature
	 *
	 * @since 3.1.0
	 * @access public
	 *
	 * @param string $feature_name
	 */
	public function remove_feature( $feature_name ) {
		unset( $this->features[ $feature_name ] );
	}

	/**
	 * Get Features
	 *
	 * @since 3.1.0
	 * @access public
	 *
	 * @param string $feature_name Optional. Default is null
	 *
	 * @return array|null
	 */
	public function get_features( $feature_name = null ) {
		return self::get_items( $this->features, $feature_name );
	}

	/**
	 * Get Active Features
	 *
	 * @since 3.1.0
	 * @access public
	 *
	 * @return array
	 */
	public function get_active_features() {
		return array_filter( $this->features, [ $this, 'is_feature_active' ], ARRAY_FILTER_USE_KEY );
	}

	/**
	 * Is Feature Active
	 *
	 * @since 3.1.0
	 * @access public
	 *
	 * @param string $feature_name
	 *
	 * @return bool
	 */
	public function is_feature_active( $feature_name ) {
		$feature = $this->get_features( $feature_name );

		if ( ! $feature ) {
			return false;
		}

		return self::STATE_ACTIVE === $this->get_feature_actual_state( $feature );
	}

	/**
	 * Set Feature Default State
	 *
	 * @since 3.1.0
	 * @access public
	 *
	 * @param string $feature_name
	 * @param int $default_state
	 */
	public function set_feature_default_state( $feature_name, $default_state ) {
		$feature = $this->get_features( $feature_name );

		if ( ! $feature ) {
			return;
		}

		$this->features[ $feature_name ]['default'] = $default_state;
	}

	/**
	 * Get Feature Option Key
	 *
	 * @since 3.1.0
	 * @access private
	 *
	 * @param string $feature_name
	 *
	 * @return string
	 */
	private function get_feature_option_key( $feature_name ) {
		return 'elementor_experiment-' . $feature_name;
	}

	private function add_default_features() {
		$this->add_feature( [
			'name' => 'e_dom_optimization',
			'title' => esc_html__( 'Optimized DOM Output', 'elementor' ),
			'description' => esc_html__( 'Developers, Please Note! This experiment includes some markup changes. If you\'ve used custom code in Elementor, you might have experienced a snippet of code not running. Turning this experiment off allows you to keep prior Elementor markup output settings, and have that lovely code running again.', 'elementor' )
				. ' <a href="https://go.elementor.com/wp-dash-legacy-optimized-dom" target="_blank">'
				. esc_html__( 'Learn More', 'elementor' ) . '</a>',
			'release_status' => self::RELEASE_STATUS_BETA,
			'new_site' => [
				'default_active' => true,
				'minimum_installation_version' => '3.1.0-beta',
			],
		] );

		$this->add_feature( [
			'name' => 'e_optimized_assets_loading',
			'title' => esc_html__( 'Improved Asset Loading', 'elementor' ),
			'description' => esc_html__( 'Please Note! The "Improved Asset Loading" mode reduces the amount of code that is loaded on the page by default. When activated, parts of the infrastructure code will be loaded dynamically, only when needed. Keep in mind that activating this experiment may cause conflicts with incompatible plugins.', 'elementor' )
				. ' <a href="https://go.elementor.com/wp-dash-improved-asset-loading/" target="_blank">'
				. esc_html__( 'Learn More', 'elementor' ) . '</a>',
			'release_status' => self::RELEASE_STATUS_ALPHA,
		] );

		$this->add_feature( [
			'name' => 'e_optimized_css_loading',
			'title' => esc_html__( 'Improved CSS Loading', 'elementor' ),
			'description' => esc_html__( 'Please Note! The “Improved CSS Loading” mode reduces the amount of CSS code that is loaded on the page by default. When activated, the CSS code will be loaded, rather inline or in a dedicated file, only when needed. Activating this experiment may cause conflicts with incompatible plugins.', 'elementor' )
				. ' <a href="https://go.elementor.com/wp-dash-improved-css-loading/" target="_blank">'
				. esc_html__( 'Learn More', 'elementor' ) . '</a>',
			'release_status' => self::RELEASE_STATUS_ALPHA,
		] );

		$this->add_feature( [
			'name' => 'e_font_icon_svg',
			'title' => esc_html__( 'Font-Awesome Inline', 'elementor' ),
			'description' => esc_html__( 'The "Font-Awesome Inline" will render the Font-Awesome icons as inline SVG without loading the Font-Awesome library and its related CSS files and fonts.', 'elementor' )
				. ' <a href="https://go.elementor.com/wp-dash-inline-font-awesome/" target="_blank">'
				. esc_html__( 'Learn More', 'elementor' ) . '</a>',
			'release_status' => self::RELEASE_STATUS_ALPHA,
		] );

		$this->add_feature( [
			'name' => 'a11y_improvements',
			'title' => esc_html__( 'Accessibility Improvements', 'elementor' ),
			'description' => esc_html__( 'An array of accessibility enhancements in Elementor pages.', 'elementor' )
				. '<br><strong>' . esc_html__( 'Please note!', 'elementor' ) . '</strong> ' . esc_html__( 'These enhancements may include some markup changes to existing elementor widgets', 'elementor' )
				. ' <a href="https://go.elementor.com/wp-dash-a11y-improvements" target="_blank">'
				. esc_html__( 'Learn More', 'elementor' ) . '</a>',
			'release_status' => self::RELEASE_STATUS_BETA,
			'new_site' => [
				'default_active' => true,
				'minimum_installation_version' => '3.1.0-beta',
			],
		] );

		$this->add_feature( [
			'name' => 'e_import_export',
			'title' => esc_html__( 'Import Export Template Kit', 'elementor' ),
			'description' => esc_html__( 'Design sites faster with a template kit that contains some or all components of a complete site, like templates, content & site settings.', 'elementor' )
				. '<br>'
				. esc_html__( 'You can import a kit and apply it to your site, or export the elements from this site to be used anywhere else.', 'elementor' ),
			'release_status' => self::RELEASE_STATUS_BETA,
			'default' => self::STATE_ACTIVE,
		] );

		$this->add_feature( [
			'name' => 'additional_custom_breakpoints',
			'title' => esc_html__( 'Additional Custom Breakpoints', 'elementor' ),
			'description' => esc_html__( 'Get pixel-perfect design for every screen size. You can now add up to 6 customizable breakpoints beyond the default desktop setting: mobile, mobile extra, tablet, tablet extra, laptop, and widescreen.', 'elementor' )
							. '<br /><strong>' . esc_html__( 'Please note! Conditioning controls on values of responsive controls is not supported when this mode is active.', 'elementor' ) . '</strong>'
				. ' <a href="https://go.elementor.com/wp-dash-additional-custom-breakpoints/" target="_blank">'
				. esc_html__( 'Learn More', 'elementor' ) . '</a>',
			'release_status' => self::RELEASE_STATUS_BETA,
			'new_site' => [
				'default_active' => true,
				'minimum_installation_version' => '3.4.0-beta',
			],
		] );
	}

	/**
	 * Init States
	 *
	 * @since 3.1.0
	 * @access private
	 */
	private function init_states() {
		$this->states = [
			self::STATE_DEFAULT => esc_html__( 'Default', 'elementor' ),
			self::STATE_ACTIVE => esc_html__( 'Active', 'elementor' ),
			self::STATE_INACTIVE => esc_html__( 'Inactive', 'elementor' ),
		];
	}

	/**
	 * Init Statuses
	 *
	 * @since 3.1.0
	 * @access private
	 */
	private function init_release_statuses() {
		$this->release_statuses = [
			self::RELEASE_STATUS_DEV => esc_html__( 'Development', 'elementor' ),
			self::RELEASE_STATUS_ALPHA => esc_html__( 'Alpha', 'elementor' ),
			self::RELEASE_STATUS_BETA => esc_html__( 'Beta', 'elementor' ),
			self::RELEASE_STATUS_RC => esc_html__( 'Release Candidate', 'elementor' ),
			self::RELEASE_STATUS_STABLE => esc_html__( 'Stable', 'elementor' ),
		];
	}

	/**
	 * Init Features
	 *
	 * @since 3.1.0
	 * @access private
	 */
	private function init_features() {
		$this->features = [];

		$this->add_default_features();

		do_action( 'elementor/experiments/default-features-registered', $this );
	}

	/**
	 * Register Settings Fields
	 *
	 * @param Settings $settings
	 *
	 * @since 3.1.0
	 * @access private
	 *
	 */
	private function register_settings_fields( Settings $settings ) {
		$features = $this->get_features();

		$fields = [];

		foreach ( $features as $feature_name => $feature ) {
			if ( ! $feature['mutable'] ) {
				unset( $features[ $feature_name ] );

				continue;
			}

			$feature_key = 'experiment-' . $feature_name;

			$fields[ $feature_key ]['label'] = $this->get_feature_settings_label_html( $feature );

			$fields[ $feature_key ]['field_args'] = $feature;

			$fields[ $feature_key ]['render'] = function( $feature ) {
				$this->render_feature_settings_field( $feature );
			};
		}

		if ( ! $features ) {
			$fields['no_features'] = [
				'label' => esc_html__( 'No available experiments', 'elementor' ),
				'field_args' => [
					'type' => 'raw_html',
					'html' => esc_html__( 'The current version of Elementor doesn\'t have any experimental features . if you\'re feeling curious make sure to come back in future versions.', 'elementor' ),
				],
			];
		}

		if ( ! Tracker::is_allow_track() ) {
			$fields += $settings->get_usage_fields();
		}

		$settings->add_tab(
			'experiments', [
				'label' => esc_html__( 'Experiments', 'elementor' ),
				'sections' => [
					'experiments' => [
						'callback' => function() {
							$this->render_settings_intro();
						},
						'fields' => $fields,
					],
				],
			]
		);
	}

	/**
	 * Render Settings Intro
	 *
	 * @since 3.1.0
	 * @access private
	 */
	private function render_settings_intro() {
		?>
		<h2><?php echo esc_html__( 'Experiments', 'elementor' ); ?></h2>
		<p>
			<?php
				printf(
					/* translators: %1$s Link open tag, %2$s: Link close tag. */
					esc_html__( 'Access new and experimental features from Elementor before they\'re officially released. As these features are still in development, they are likely to change, evolve or even be removed altogether. %1$sLearn More.%2$s', 'elementor' ),
					'<a href="https://go.elementor.com/wp-dash-experiments/" target="_blank">',
					'</a>'
				);
			?>
		</p>
		<p><?php echo esc_html__( 'To use an experiment on your site, simply click on the dropdown next to it and switch to Active. You can always deactivate them at any time.', 'elementor' ); ?></p>
		<p>
			<?php
				printf(
					/* translators: %1$s Link open tag, %2$s: Link close tag. */
					esc_html__( 'Your feedback is important - %1$shelp us%2$s improve these features by sharing your thoughts and inputs.', 'elementor' ),
					'<a href="https://go.elementor.com/wp-dash-experiments-report-an-issue/" target="_blank">',
					'</a>'
				);
			?>
		</p>
		<?php
	}

	/**
	 * Render Feature Settings Field
	 *
	 * @since 3.1.0
	 * @access private
	 *
	 * @param array $feature
	 */
	private function render_feature_settings_field( array $feature ) {
		?>
		<div class="e-experiment__content">
			<select id="e-experiment-<?php echo $feature['name']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" class="e-experiment__select" name="<?php echo $this->get_feature_option_key( $feature['name'] ); ?>">
				<?php foreach ( $this->states as $state_key => $state_title ) { ?>
					<option value="<?php echo $state_key; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" <?php selected( $state_key, $feature['state'] ); ?>><?php echo $state_title; ?></option>
				<?php } ?>
			</select>
			<p class="description"><?php echo $feature['description']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
			<div class="e-experiment__status"><?php echo sprintf( esc_html__( 'Status: %s', 'elementor' ), $this->release_statuses[ $feature['release_status'] ] );  // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
		</div>
		<?php
	}

	/**
	 * Get Feature Settings Label HTML
	 *
	 * @since 3.1.0
	 * @access private
	 *
	 * @param array $feature
	 *
	 * @return string
	 */
	private function get_feature_settings_label_html( array $feature ) {
		ob_start();

		$is_feature_active = $this->is_feature_active( $feature['name'] );

		$indicator_classes = 'e-experiment__title__indicator';

		if ( $is_feature_active ) {
			$indicator_classes .= ' e-experiment__title__indicator--active';
		}

		if ( self::STATE_DEFAULT === $feature['state'] ) {
			$indicator_tooltip = $is_feature_active ? esc_html__( 'Active by default', 'elementor' ) : esc_html__( 'Inactive by default', 'elementor' );
		} else {
			$indicator_tooltip = self::STATE_ACTIVE === $feature['state'] ? esc_html__( 'Active', 'elementor' ) : esc_html__( 'Inactive', 'elementor' );
		}
		?>
		<div class="e-experiment__title">
			<div class="<?php echo $indicator_classes; ?>" data-tooltip="<?php echo $indicator_tooltip; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>"></div>
			<label class="e-experiment__title__label" for="e-experiment-<?php echo $feature['name']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>"><?php echo $feature['title']; ?></label>
		</div>
		<?php

		return ob_get_clean();
	}

	/**
	 * Get Feature Settings Label HTML
	 *
	 * @since 3.1.0
	 * @access private
	 *
	 * @param string $feature_name
	 *
	 * @return int
	 */
	private function get_saved_feature_state( $feature_name ) {
		return get_option( $this->get_feature_option_key( $feature_name ) );
	}

	/**
	 * Get Feature Actual State
	 *
	 * @since 3.1.0
	 * @access private
	 *
	 * @param array $feature
	 *
	 * @return string
	 */
	private function get_feature_actual_state( array $feature ) {
		if ( self::STATE_DEFAULT !== $feature['state'] ) {
			return $feature['state'];
		}

		return $feature['default'];
	}

	/**
	 * On Feature State Change
	 *
	 * @since 3.1.0
	 * @access private
	 *
	 * @param array $old_feature_data
	 * @param string $new_state
	 */
	private function on_feature_state_change( array $old_feature_data, $new_state ) {
		$this->features[ $old_feature_data['name'] ]['state'] = $new_state;

		$new_feature_data = $this->get_features( $old_feature_data['name'] );

		$actual_old_state = $this->get_feature_actual_state( $old_feature_data );

		$actual_new_state = $this->get_feature_actual_state( $new_feature_data );

		if ( $actual_old_state === $actual_new_state ) {
			return;
		}

		Plugin::$instance->files_manager->clear_cache();

		if ( $new_feature_data['on_state_change'] ) {
			$new_feature_data['on_state_change']( $actual_old_state, $actual_new_state );
		}
	}

	public function __construct() {
		$this->init_states();

		$this->init_release_statuses();

		$this->init_features();

		if ( is_admin() ) {
			$page_id = Settings::PAGE_ID;

			add_action( "elementor/admin/after_create_settings/{$page_id}", function( Settings $settings ) {
				$this->register_settings_fields( $settings );
			}, 11 );
		}
	}
}
