<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Jejeresources_Umami_Settings {

	/**
	 * @var Jejeresources_Umami_Api_Client
	 */
	private $api_client;

	public function __construct( Jejeresources_Umami_Api_Client $api_client ) {
		$this->api_client = $api_client;
	}

	/**
	 * Fügt Einstellungsseite hinzu
	 */
	public function add_settings_page() {
		add_options_page(
			'Statistiken',
			'Statistiken',
			'manage_options',
			'umami-stats-settings',
			array( $this, 'display_settings_page' )
		);
	}

	/**
	 * Zeigt die Einstellungsseite
	 */
	public function display_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Du hast keine Berechtigung, diese Seite zu sehen.' ) );
		}

		// Test Connection Button Handler
		if ( isset( $_POST['umami_test_connection'] ) && check_admin_referer( 'umami_test_connection' ) ) {
			delete_transient( 'umami_api_token' ); // Clear cached token
			$token = $this->api_client->get_api_token();
			if ( $token ) {
				echo '<div class="notice notice-success"><p>✓ Verbindung erfolgreich!</p></div>';
			} else {
				echo '<div class="notice notice-error"><p>✗ Verbindung fehlgeschlagen. Bitte überprüfe deine Zugangsdaten.</p></div>';
			}
		}

		?>
		<div class="wrap">
			<h1>Statistiken - Einstellungen</h1>

			<form method="post" action="options.php">
				<?php
				settings_fields( 'umami_stats_settings' );
				do_settings_sections( 'umami-stats-settings' );
				submit_button();
				?>
			</form>

			<hr>

			<h2>Verbindung testen</h2>
			<form method="post">
				<?php wp_nonce_field( 'umami_test_connection' ); ?>
				<p>Teste die Verbindung zu deinem Umami-Server.</p>
				<button type="submit" name="umami_test_connection" class="button">Verbindung testen</button>
			</form>
		</div>
		<?php
	}

	/**
	 * Registriert Plugin-Einstellungen (nur für Admins)
	 */
	public function register_settings() {
		// Extra Sicherheitsprüfung: Nur Admins dürfen Settings registrieren
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Umami API Credentials
		register_setting( 'umami_stats_settings', 'umami_username', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		) );

		register_setting( 'umami_stats_settings', 'umami_password', array(
			'type'              => 'string',
			'sanitize_callback' => array( $this, 'sanitize_password' ),
			'default'           => '',
		) );

		register_setting( 'umami_stats_settings', 'umami_website_id', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		) );

		// Erlaubte Rollen
		register_setting( 'umami_stats_settings', 'umami_allowed_roles', array(
			'type'              => 'array',
			'sanitize_callback' => array( $this, 'sanitize_roles' ),
			'default'           => array( 'administrator', 'editor' ),
		) );

		// Gradient Farben
		register_setting( 'umami_stats_settings', 'umami_gradient_start', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_hex_color',
			'default'           => '#667eea',
		) );

		register_setting( 'umami_stats_settings', 'umami_gradient_end', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_hex_color',
			'default'           => '#764ba2',
		) );

		register_setting( 'umami_stats_settings', 'umami_button_text_color', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_hex_color',
			'default'           => '#ffffff',
		) );

		// API Settings Section
		add_settings_section(
			'umami_stats_api_section',
			'API Zugangsdaten',
			array( $this, 'api_section_callback' ),
			'umami-stats-settings'
		);

		// Username
		add_settings_field(
			'umami_username',
			'Benutzername',
			array( $this, 'username_field_callback' ),
			'umami-stats-settings',
			'umami_stats_api_section'
		);

		// Password
		add_settings_field(
			'umami_password',
			'Passwort',
			array( $this, 'password_field_callback' ),
			'umami-stats-settings',
			'umami_stats_api_section'
		);

		// Website ID
		add_settings_field(
			'umami_website_id',
			'Website-ID',
			array( $this, 'website_id_field_callback' ),
			'umami-stats-settings',
			'umami_stats_api_section'
		);

		// Design Settings Section
		add_settings_section(
			'umami_stats_design_section',
			'Design & Berechtigungen',
			array( $this, 'design_section_callback' ),
			'umami-stats-settings'
		);

		// Rollen Feld
		add_settings_field(
			'umami_allowed_roles',
			'Berechtigte Rollen',
			array( $this, 'allowed_roles_field_callback' ),
			'umami-stats-settings',
			'umami_stats_design_section'
		);

		// Gradient Farben Feld
		add_settings_field(
			'umami_gradient_colors',
			'Gradient Farben',
			array( $this, 'gradient_colors_field_callback' ),
			'umami-stats-settings',
			'umami_stats_design_section'
		);

		// Footer Settings Section
		add_settings_section(
			'umami_stats_footer_section',
			'Footer (optional)',
			array( $this, 'footer_section_callback' ),
			'umami-stats-settings'
		);

		// Footer Logo
		register_setting( 'umami_stats_settings', 'umami_footer_logo', array(
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'default'           => 0,
		) );

		add_settings_field(
			'umami_footer_logo',
			'Logo',
			array( $this, 'footer_logo_field_callback' ),
			'umami-stats-settings',
			'umami_stats_footer_section'
		);

		// Footer Text
		register_setting( 'umami_stats_settings', 'umami_footer_text', array(
			'type'              => 'string',
			'sanitize_callback' => 'wp_kses_post',
			'default'           => '',
		) );

		add_settings_field(
			'umami_footer_text',
			'Text',
			array( $this, 'footer_text_field_callback' ),
			'umami-stats-settings',
			'umami_stats_footer_section'
		);

		// Footer Button URL
		register_setting( 'umami_stats_settings', 'umami_footer_button_url', array(
			'type'              => 'string',
			'sanitize_callback' => 'esc_url_raw',
			'default'           => '',
		) );

		// Footer Button Text
		register_setting( 'umami_stats_settings', 'umami_footer_button_text', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		) );

		add_settings_field(
			'umami_footer_button',
			'Button',
			array( $this, 'footer_button_field_callback' ),
			'umami-stats-settings',
			'umami_stats_footer_section'
		);
	}

	/**
	 * Sanitize Rollen Array
	 */
	public function sanitize_roles( $input ) {
		if ( ! is_array( $input ) ) {
			return array( 'administrator' );
		}

		$wp_roles    = wp_roles();
		$valid_roles = array();

		foreach ( $input as $role ) {
			if ( isset( $wp_roles->roles[ $role ] ) ) {
				$valid_roles[] = $role;
			}
		}

		if ( empty( $valid_roles ) ) {
			$valid_roles[] = 'administrator';
		}

		return $valid_roles;
	}

	/**
	 * Sanitize und verschlüssele Passwort
	 */
	public function sanitize_password( $input ) {
		// Leeres Feld = Passwort nicht ändern, bestehendes behalten
		if ( empty( $input ) ) {
			return get_option( 'umami_password', '' );
		}
		// Bereits verschlüsselt
		if ( strpos( $input, 'enc:' ) === 0 ) {
			return $input;
		}
		// Neues Passwort verschlüsseln
		return 'enc:' . Jejeresources_Umami_Encryption::encrypt_password( sanitize_text_field( $input ) );
	}

	/**
	 * Callback für API Section
	 */
	public function api_section_callback() {
		echo '<p>Trage deine Umami-Zugangsdaten ein. Diese werden verwendet um die API zu authentifizieren.</p>';
		echo '<p><strong>API URL:</strong> ' . esc_html( $this->api_client->get_api_url() ) . '</p>';
	}

	/**
	 * Callback für Design Section
	 */
	public function design_section_callback() {
		echo '<p>Passe das Design und die Berechtigungen an.</p>';
	}

	/**
	 * Callback für Username Feld
	 */
	public function username_field_callback() {
		$value = get_option( 'umami_username', '' );
		?>
		<input 
			type="text" 
			name="umami_username" 
			value="<?php echo esc_attr( $value ); ?>" 
			class="regular-text"
			placeholder="admin"
			autocomplete="off"
		>
		<p class="description">Dein Umami-Benutzername</p>
		<?php
	}

	/**
	 * Callback für Password Feld
	 */
	public function password_field_callback() {
		$value        = get_option( 'umami_password', '' );
		$is_encrypted = strpos( $value, 'enc:' ) === 0;
		?>
		<input 
			type="password" 
			name="umami_password" 
			value="<?php echo $is_encrypted ? '' : esc_attr( $value ); ?>" 
			class="regular-text"
			placeholder="<?php echo $is_encrypted ? '••••••••••••••••' : '••••••••'; ?>"
			autocomplete="new-password"
		>
		<p class="description">
			Dein Umami-Passwort (wird verschlüsselt mit WordPress-Salts gespeichert)
			<?php if ( $is_encrypted ) : ?>
				<br><strong style="color: #00a32a;">✓ Passwort ist verschlüsselt gespeichert</strong>
			<?php endif; ?>
		</p>
		<?php
	}

	/**
	 * Callback für Website ID Feld
	 */
	public function website_id_field_callback() {
		$value = get_option( 'umami_website_id', '' );
		?>
		<input 
			type="text" 
			name="umami_website_id" 
			value="<?php echo esc_attr( $value ); ?>" 
			class="regular-text"
			placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
		>
		<p class="description">
			Die Website-ID findest du in Umami unter Settings → Websites → Website auswählen.<br>
			Die ID steht in der URL: <code>/settings/websites/<strong>DIESE-ID</strong></code>
		</p>
		<?php
	}

	/**
	 * Callback für Erlaubte Rollen
	 */
	public function allowed_roles_field_callback() {
		$selected_roles = get_option( 'umami_allowed_roles', array( 'administrator', 'editor' ) );
		$wp_roles       = wp_roles();

		echo '<fieldset>';
		foreach ( $wp_roles->roles as $role_key => $role ) {
			$checked = in_array( $role_key, $selected_roles ) ? 'checked' : '';
			?>
			<label style="display: block; margin-bottom: 8px;">
				<input type="checkbox" 
					   name="umami_allowed_roles[]" 
					   value="<?php echo esc_attr( $role_key ); ?>"
					   <?php echo $checked; ?>>
				<?php echo esc_html( $role['name'] ); ?>
			</label>
			<?php
		}
		echo '</fieldset>';
		?>
		<p class="description">Wähle welche Rollen das Dashboard Widget und die Analytics-Seite sehen können.</p>
		<?php
	}

	/**
	 * Callback für Gradient Farben
	 */
	public function gradient_colors_field_callback() {
		$gradient_start    = get_option( 'umami_gradient_start', '#667eea' );
		$gradient_end      = get_option( 'umami_gradient_end', '#764ba2' );
		$button_text_color = get_option( 'umami_button_text_color', '#ffffff' );
		?>
		<div style="display: flex; gap: 20px; flex-wrap: wrap;">
			<div>
				<label style="display: block; margin-bottom: 5px; font-weight: 500;">Start-Farbe</label>
				<input type="color" 
					   name="umami_gradient_start" 
					   id="umami_gradient_start"
					   value="<?php echo esc_attr( $gradient_start ); ?>"
					   style="width: 80px; height: 40px; cursor: pointer;">
				<div style="margin-top: 3px; font-size: 11px; color: #666;">
					<?php echo esc_html( $gradient_start ); ?>
				</div>
			</div>
			<div>
				<label style="display: block; margin-bottom: 5px; font-weight: 500;">End-Farbe</label>
				<input type="color" 
					   name="umami_gradient_end"
					   id="umami_gradient_end" 
					   value="<?php echo esc_attr( $gradient_end ); ?>"
					   style="width: 80px; height: 40px; cursor: pointer;">
				<div style="margin-top: 3px; font-size: 11px; color: #666;">
					<?php echo esc_html( $gradient_end ); ?>
				</div>
			</div>
			<div>
				<label style="display: block; margin-bottom: 5px; font-weight: 500;">Text-Farbe</label>
				<input type="color" 
					   name="umami_button_text_color"
					   id="umami_button_text_color" 
					   value="<?php echo esc_attr( $button_text_color ); ?>"
					   style="width: 80px; height: 40px; cursor: pointer;">
				<div style="margin-top: 3px; font-size: 11px; color: #666;">
					<?php echo esc_html( $button_text_color ); ?>
				</div>
			</div>
			<div>
				<label style="display: block; margin-bottom: 5px; font-weight: 500;">Vorschau</label>
				<div id="umami_button_preview" 
					 style="padding: 10px 20px; 
							border-radius: 6px; 
							box-shadow: 0 2px 8px rgba(0,0,0,0.15);
							background: linear-gradient(135deg, <?php echo esc_attr( $gradient_start ); ?> 0%, 
							<?php echo esc_attr( $gradient_end ); ?> 100%);
							color: <?php echo esc_attr( $button_text_color ); ?>;
							font-weight: 500;
							text-align: center;
							min-width: 140px;">
					Beispieltext
				</div>
			</div>
		</div>
		<p class="description" style="margin-top: 10px;">
			Passe die Gradient-Farben an dein Corporate Design an.
		</p>

		<script>
		(function() {
			var startInput = document.getElementById('umami_gradient_start');
			var endInput = document.getElementById('umami_gradient_end');
			var textInput = document.getElementById('umami_button_text_color');
			var preview = document.getElementById('umami_button_preview');

			function updatePreview() {
				if (preview && startInput && endInput && textInput) {
					var startColor = startInput.value;
					var endColor = endInput.value;
					var textColor = textInput.value;

					preview.style.background = 'linear-gradient(135deg, ' + startColor + ' 0%, ' + endColor + ' 100%)';
					preview.style.color = textColor;
				}
			}

			if (startInput && endInput && textInput) {
				startInput.addEventListener('input', updatePreview);
				endInput.addEventListener('input', updatePreview);
				textInput.addEventListener('input', updatePreview);
			}
		})();
		</script>
		<?php
	}

	/**
	 * Callback für Footer Section
	 */
	public function footer_section_callback() {
		echo '<p>Optionaler Footer unterhalb der Analytics-Charts. Zeige dein Branding und verlinke zur Umami-Instanz.</p>';
	}

	/**
	 * Callback für Footer Logo
	 */
	public function footer_logo_field_callback() {
		$logo_id  = get_option( 'umami_footer_logo', 0 );
		$logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : '';
		?>
		<div id="umami-logo-preview" style="margin-bottom: 10px;">
			<?php if ( $logo_url ) : ?>
				<img src="<?php echo esc_url( $logo_url ); ?>" style="max-height: 60px; width: auto;">
			<?php endif; ?>
		</div>
		<input type="hidden" name="umami_footer_logo" id="umami_footer_logo" value="<?php echo esc_attr( $logo_id ); ?>">
		<button type="button" class="button" id="umami-upload-logo">Logo auswählen</button>
		<?php if ( $logo_id ) : ?>
			<button type="button" class="button" id="umami-remove-logo" style="color: #d63638;">Entfernen</button>
		<?php else : ?>
			<button type="button" class="button" id="umami-remove-logo" style="color: #d63638; display: none;">Entfernen</button>
		<?php endif; ?>
		<p class="description">Optional ein Logo für den Footer (empfohlen: max. 200px breit)</p>
		<script>
		jQuery(document).ready(function($) {
			var frame;
			$('#umami-upload-logo').on('click', function(e) {
				e.preventDefault();
				if (frame) { frame.open(); return; }
				frame = wp.media({
					title: 'Logo auswählen',
					button: { text: 'Logo verwenden' },
					multiple: false,
					library: { type: 'image' }
				});
				frame.on('select', function() {
					var attachment = frame.state().get('selection').first().toJSON();
					$('#umami_footer_logo').val(attachment.id);
					var url = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;
					$('#umami-logo-preview').html('<img src="' + url + '" style="max-height: 60px; width: auto;">');
					$('#umami-remove-logo').show();
				});
				frame.open();
			});
			$('#umami-remove-logo').on('click', function(e) {
				e.preventDefault();
				$('#umami_footer_logo').val(0);
				$('#umami-logo-preview').html('');
				$(this).hide();
			});
		});
		</script>
		<?php
	}

	/**
	 * Callback für Footer Text
	 */
	public function footer_text_field_callback() {
		$content = get_option( 'umami_footer_text', '' );
		wp_editor( $content, 'umami_footer_text', array(
			'textarea_name' => 'umami_footer_text',
			'textarea_rows' => 5,
			'media_buttons' => false,
			'teeny'         => true,
			'quicktags'     => true,
		) );
		echo '<p class="description">Optionaler Text im Footer (z.B. "Bereitgestellt von Firma XY")</p>';
	}

	/**
	 * Callback für Footer Button
	 */
	public function footer_button_field_callback() {
		$button_text = get_option( 'umami_footer_button_text', '' );
		$button_url  = get_option( 'umami_footer_button_url', '' );
		?>
		<div style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-start;">
			<div>
				<label style="display: block; margin-bottom: 5px; font-weight: 500;">Button-Text</label>
				<input type="text"
					   name="umami_footer_button_text"
					   value="<?php echo esc_attr( $button_text ); ?>"
					   class="regular-text"
					   placeholder="Zu Analytics">
			</div>
			<div>
				<label style="display: block; margin-bottom: 5px; font-weight: 500;">Button-URL</label>
				<input type="url"
					   name="umami_footer_button_url"
					   value="<?php echo esc_attr( $button_url ); ?>"
					   class="regular-text"
					   placeholder="https://analytics.example.com">
			</div>
		</div>
		<p class="description">Optionaler Button, z.B. Link zu deiner Umami-Instanz. Leer lassen um keinen Button anzuzeigen.</p>
		<?php
	}
}
