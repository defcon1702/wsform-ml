<?php
if (!defined('ABSPATH')) {
	exit;
}
?>

<div class="wrap wsform-ml-admin">
	<h1><?php _e('WSForm Multilingual', 'wsform-ml'); ?></h1>
	
	<div class="wsform-ml-header">
		<div class="wsform-ml-languages">
			<strong><?php _e('Verfügbare Sprachen:', 'wsform-ml'); ?></strong>
			<div id="wsform-ml-language-list"></div>
		</div>
	</div>

	<div class="wsform-ml-content">
		<div class="wsform-ml-sidebar">
			<div class="wsform-ml-forms-list">
				<h2><?php _e('Formulare', 'wsform-ml'); ?></h2>
				<div id="wsform-ml-forms-loading" class="wsform-ml-loading">
					<?php _e('Lade Formulare...', 'wsform-ml'); ?>
				</div>
				<div id="wsform-ml-forms-container"></div>
			</div>
		</div>

		<div class="wsform-ml-main">
			<div id="wsform-ml-welcome" class="wsform-ml-welcome">
				<div class="wsform-ml-welcome-icon">
					<span class="dashicons dashicons-translation"></span>
				</div>
				<h2><?php _e('Willkommen bei WSForm Multilingual', 'wsform-ml'); ?></h2>
				<p><?php _e('Wählen Sie ein Formular aus der Liste, um mit der Übersetzung zu beginnen.', 'wsform-ml'); ?></p>
				<div class="wsform-ml-features">
					<div class="wsform-ml-feature">
						<span class="dashicons dashicons-search"></span>
						<h3><?php _e('Auto-Discovery', 'wsform-ml'); ?></h3>
						<p><?php _e('Automatisches Scannen aller Formularfelder', 'wsform-ml'); ?></p>
					</div>
					<div class="wsform-ml-feature">
						<span class="dashicons dashicons-admin-generic"></span>
						<h3><?php _e('Komplexe Formulare', 'wsform-ml'); ?></h3>
						<p><?php _e('Support für Repeater, Conditional Logic, Select-Optionen', 'wsform-ml'); ?></p>
					</div>
					<div class="wsform-ml-feature">
						<span class="dashicons dashicons-warning"></span>
						<h3><?php _e('Warnungen', 'wsform-ml'); ?></h3>
						<p><?php _e('Automatische Erkennung fehlender Übersetzungen', 'wsform-ml'); ?></p>
					</div>
				</div>
			</div>

			<div id="wsform-ml-form-detail" class="wsform-ml-form-detail" style="display: none;">
				<div class="wsform-ml-form-header">
					<h2 id="wsform-ml-form-title"></h2>
					<div class="wsform-ml-form-actions">
						<button id="wsform-ml-scan-btn" class="button button-primary">
							<span class="dashicons dashicons-update"></span>
							<?php _e('Formular scannen', 'wsform-ml'); ?>
						</button>
					</div>
				</div>

				<div id="wsform-ml-scan-result" class="wsform-ml-notice" style="display: none;"></div>

				<div class="wsform-ml-stats">
					<h3><?php _e('Übersetzungsstatistik', 'wsform-ml'); ?></h3>
					<div id="wsform-ml-stats-container"></div>
				</div>

				<div class="wsform-ml-translation-section">
					<div class="wsform-ml-language-tabs">
						<h3><?php _e('Übersetzungen', 'wsform-ml'); ?></h3>
						<div id="wsform-ml-language-tabs"></div>
					</div>

					<div id="wsform-ml-missing-warning" class="wsform-ml-warning" style="display: none;">
						<span class="dashicons dashicons-warning"></span>
						<span id="wsform-ml-missing-count"></span>
					</div>

					<div id="wsform-ml-fields-container"></div>
				</div>
			</div>
		</div>
	</div>
</div>
