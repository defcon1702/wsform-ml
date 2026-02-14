(function () {
	'use strict';

	const WSFormML = {
		currentFormId: null,
		currentLanguage: null,
		forms: [],
		fields: [],
		translations: {},

		init() {
			this.renderLanguages();
			this.loadForms();
			this.bindEvents();
		},

		bindEvents() {
			document.getElementById('wsform-ml-scan-btn')?.addEventListener('click', () => {
				this.scanForm(this.currentFormId);
			});
		},

		renderLanguages() {
			const container = document.getElementById('wsform-ml-language-list');
			if (!container) {
				console.error('Language list container not found');
				return;
			}

			const html = wsformML.languages.map(lang => `
				<span class="wsform-ml-language-badge ${lang.is_default ? 'is-default' : ''}">
					${lang.flag_url && lang.flag_url !== '' ? `<img src="${lang.flag_url}" alt="${lang.name}" style="width: 16px; height: 12px;">` : ''}
					${lang.name}
					${lang.is_default ? '(Standard)' : ''}
				</span>
			`).join('');

			container.innerHTML = html;
		},

		async loadForms() {
			const container = document.getElementById('wsform-ml-forms-container');
			const loading = document.getElementById('wsform-ml-forms-loading');

			try {
				const response = await fetch(`${wsformML.restUrl}/forms`, {
					headers: {
						'X-WP-Nonce': wsformML.nonce
					}
				});

				if (!response.ok) {
					const responseText = await response.text();

					let errorData = {};
					try {
						errorData = JSON.parse(responseText);
					} catch (e) { }

					throw new Error(errorData.message || responseText || 'Failed to load forms');
				}

				this.forms = await response.json();

				loading.style.display = 'none';
				this.renderForms();

			} catch (error) {
				loading.innerHTML = `<p style="color: #d63638; padding: 20px;">Fehler beim Laden der Formulare: ${error.message}</p>`;
			}
		},

		renderForms() {
			const container = document.getElementById('wsform-ml-forms-container');
			if (!container) return;

			if (this.forms.length === 0) {
				container.innerHTML = '<p style="padding: 20px; text-align: center; color: #646970;">Keine Formulare gefunden</p>';
				return;
			}

			container.innerHTML = this.forms.map(form => {
				const isScanned = form.cached_fields_count > 0;
				const lastScanned = form.last_scanned ? new Date(form.last_scanned).toLocaleDateString('de-DE') : '';

				return `
					<div class="wsform-ml-form-item" data-form-id="${String(form.id)}">
						<div class="wsform-ml-form-item-title">${this.escapeHtml(form.label)}</div>
						<div class="wsform-ml-form-item-meta">
							<span>ID: ${String(form.id)}</span>
							${isScanned ? `<span>${form.cached_fields_count} Felder gecacht</span>` : ''}
							${lastScanned ? `<span>Gescannt: ${lastScanned}</span>` : ''}
						</div>
						<span class="wsform-ml-form-item-badge ${isScanned ? 'scanned' : 'not-scanned'}">
							${isScanned ? '✓ Gescannt' : '⚠ Nicht gescannt'}
						</span>
					</div>
				`;
			}).join('');

			container.querySelectorAll('.wsform-ml-form-item').forEach(item => {
				item.addEventListener('click', (e) => {
					const formId = e.currentTarget.dataset.formId;
					this.selectForm(formId);
				});
			});
		},

		async selectForm(formId) {
			document.querySelectorAll('.wsform-ml-form-item').forEach(item => {
				item.classList.remove('active');
			});
			document.querySelector(`[data-form-id="${formId}"]`)?.classList.add('active');

			this.currentFormId = formId;
			const form = this.forms.find(f => String(f.id) === String(formId));

			if (!form) {
				return;
			}

			document.getElementById('wsform-ml-welcome').style.display = 'none';
			document.getElementById('wsform-ml-form-detail').style.display = 'block';
			document.getElementById('wsform-ml-form-title').textContent = form.label || `Form ${formId}`;

			await this.loadFormData(formId);
		},

		async loadFormData(formId) {
			try {
				const [fields, stats] = await Promise.all([
					this.fetchFields(formId),
					this.fetchStats(formId)
				]);

				this.fields = fields;
				this.renderStats(stats);
				this.renderLanguageTabs();

				if (this.currentLanguage) {
					await this.loadTranslations(formId, this.currentLanguage);
				} else if (wsformML.languages.length > 0) {
					const nonDefaultLang = wsformML.languages.find(l => !l.is_default);
					if (nonDefaultLang) {
						this.selectLanguage(nonDefaultLang.code);
					}
				}

			} catch (error) {
				loading.innerHTML = `<p style="color: #d63638; padding: 20px;">Fehler beim Laden der Formulardaten: ${error.message}</p>`;
			}
		},

		async fetchFields(formId) {
			const response = await fetch(`${wsformML.restUrl}/forms/${formId}/fields`, {
				headers: { 'X-WP-Nonce': wsformML.nonce }
			});
			if (!response.ok) throw new Error('Failed to fetch fields');
			return response.json();
		},

		async fetchStats(formId) {
			const response = await fetch(`${wsformML.restUrl}/forms/${formId}/stats`, {
				headers: { 'X-WP-Nonce': wsformML.nonce }
			});
			if (!response.ok) throw new Error('Failed to fetch stats');
			return response.json();
		},

		renderStats(stats) {
			const container = document.getElementById('wsform-ml-stats-container');
			if (!container) return;

			const languages = Object.entries(stats.languages);

			container.innerHTML = languages.map(([code, lang]) => {
				const percentage = lang.percentage || 0;
				return `
					<div class="wsform-ml-stat-card">
						<div class="wsform-ml-stat-label">${this.escapeHtml(lang.name)}</div>
						<div class="wsform-ml-stat-value">${lang.translated} / ${lang.total}</div>
						<div class="wsform-ml-stat-progress">
							<div class="wsform-ml-stat-progress-bar" style="width: ${percentage}%"></div>
						</div>
					</div>
				`;
			}).join('');
		},

		renderLanguageTabs() {
			const container = document.getElementById('wsform-ml-language-tabs');
			if (!container) return;

			container.innerHTML = wsformML.languages.map(lang => `
				<button class="wsform-ml-language-tab" data-lang="${lang.code}">
					${lang.flag_url && lang.flag_url !== '' ? `<img src="${lang.flag_url}" alt="${lang.name}" style="width: 16px; height: 12px; margin-right: 5px;">` : ''}
					${lang.name}
					${lang.is_default ? ' (Standard)' : ''}
				</button>
			`).join('');

			container.querySelectorAll('.wsform-ml-language-tab').forEach(tab => {
				tab.addEventListener('click', (e) => {
					const langCode = e.currentTarget.dataset.lang;
					this.selectLanguage(langCode);
				});
			});

			if (wsformML.languages.length > 0 && !this.currentLanguage) {
				this.selectLanguage(wsformML.languages[0].code);
			}
		},

		async selectLanguage(langCode) {
			document.querySelectorAll('.wsform-ml-language-tab').forEach(tab => {
				tab.classList.remove('active');
			});
			document.querySelector(`[data-lang="${langCode}"]`)?.classList.add('active');

			this.currentLanguage = langCode;
			await this.loadTranslations(this.currentFormId, langCode);
		},

		async loadTranslations(formId, langCode) {
			try {
				const [translations, missing] = await Promise.all([
					this.fetchTranslations(formId, langCode),
					this.fetchMissingTranslations(formId, langCode)
				]);

				this.translations[langCode] = {};
				translations.forEach(trans => {
					const key = `${trans.field_path}::${trans.property_type}`;
					this.translations[langCode][key] = trans;
				});

				this.renderFields(missing);

			} catch (error) {
				loading.innerHTML = `<p style="color: #d63638; padding: 20px;">Fehler beim Laden der Übersetzungen: ${error.message}</p>`;
			}
		},

		async fetchTranslations(formId, langCode) {
			const response = await fetch(`${wsformML.restUrl}/forms/${formId}/translations?language=${langCode}`, {
				headers: { 'X-WP-Nonce': wsformML.nonce }
			});
			if (!response.ok) throw new Error('Failed to fetch translations');
			return response.json();
		},

		async fetchMissingTranslations(formId, langCode) {
			const response = await fetch(`${wsformML.restUrl}/forms/${formId}/translations/missing?language=${langCode}`, {
				headers: { 'X-WP-Nonce': wsformML.nonce }
			});
			if (!response.ok) throw new Error('Failed to fetch missing translations');
			return response.json();
		},

		renderFields(missingTranslations) {
			const container = document.getElementById('wsform-ml-fields-container');
			const warningContainer = document.getElementById('wsform-ml-missing-warning');
			const missingCount = document.getElementById('wsform-ml-missing-count');

			if (!container) return;

			warningContainer.style.display = 'none';

			const fieldGroups = new Map();

			this.fields.forEach(field => {
				if (!fieldGroups.has(field.field_id)) {
					fieldGroups.set(field.field_id, {
						field: field,
						properties: []
					});
				}
			});

			this.fields.forEach(field => {
				const props = field.translatable_properties || [];
				props.forEach(prop => {
					// Für Options: Nutze vollständigen Pfad aus Scanner (prop.path)
					// Für andere Properties: Nutze field.field_path
					const fullPath = prop.type === 'option' && prop.path
						? `${field.field_path}.${prop.path}`
						: field.field_path;

					fieldGroups.get(field.field_id).properties.push({
						...prop,
						field_path: fullPath
					});
				});
			});

			// Globaler Speicher-Button
			const globalSaveBtn = `
				<div class="wsform-ml-global-actions">
					<button class="button button-large wsform-ml-toggle-all" id="wsform-ml-toggle-all-btn">
						<span class="dashicons dashicons-editor-expand"></span>
						Alle ausklappen
					</button>
					<button class="button button-primary button-large wsform-ml-save-all" id="wsform-ml-save-all-btn">
						<span class="dashicons dashicons-arrow-down-alt"></span>
						Alle Änderungen speichern
					</button>
				</div>
			`;

			container.innerHTML = globalSaveBtn + Array.from(fieldGroups.values()).map(group => {
				const field = group.field;
				const fieldBadge = field.field_type === 'group' ? 'tab' : (field.is_repeater ? 'repeater' : (field.has_options ? 'option' : ''));

				// Prüfe ob alle Properties übersetzt sind
				const hasUntranslated = group.properties.some(prop => {
					const key = `${prop.field_path}::${prop.type}`;
					const translation = this.translations[this.currentLanguage]?.[key];
					return !translation?.translated_value;
				});
				const untranslatedClass = hasUntranslated ? ' wsform-ml-field-untranslated' : '';

				return `
						<div class="wsform-ml-field-group">
							<div class="wsform-ml-field-header${untranslatedClass}" data-field-id="${field.field_id}">
								<div>
									<div class="wsform-ml-field-title">
										${this.escapeHtml(field.field_label || `Field ${field.field_id}`)}
										${fieldBadge ? `<span class="wsform-ml-field-badge ${fieldBadge}">${fieldBadge}</span>` : ''}
									</div>
									<div class="wsform-ml-field-meta">
										${field.field_type} • ID: ${field.field_id}
									</div>
								</div>
								<span class="dashicons dashicons-arrow-down-alt2"></span>
							</div>
							<div class="wsform-ml-field-body">
								${group.properties.map(prop => this.renderProperty(field, prop)).join('')}
							</div>
						</div>
					`;
			}).join('');

			container.querySelectorAll('.wsform-ml-field-header').forEach(header => {
				header.addEventListener('click', (e) => {
					const body = e.currentTarget.nextElementSibling;
					body.classList.toggle('open');
				});
			});
		},

		renderProperty(field, prop) {
			const key = `${prop.field_path}::${prop.type}`;
			const translation = this.translations[this.currentLanguage]?.[key];
			const translatedValue = translation?.translated_value || '';

			return `
				<div class="wsform-ml-property" data-property-key="${key}">
					<label class="wsform-ml-property-label">${this.getPropertyLabel(prop.type)}</label>
					<div class="wsform-ml-property-original">${this.escapeHtml(prop.value)}</div>
					<div class="wsform-ml-property-input">
						<textarea 
							class="wsform-ml-translation-input"
							data-form-id="${this.currentFormId}"
							data-field-id="${field.field_id}"
							data-field-path="${prop.field_path}"
							data-property-type="${prop.type}"
							data-original="${this.escapeHtml(prop.value)}"
							placeholder="Übersetzung eingeben..."
						>${this.escapeHtml(translatedValue)}</textarea>
					</div>
				</div>
			`;
		},

		getPropertyLabel(type) {
			const labels = {
				'label': 'Label',
				'group_label': 'Tab-Name',
				'placeholder': 'Platzhalter',
				'help': 'Hilfetext',
				'invalid_feedback': 'Fehlermeldung',
				'option': 'Option',
				'text_editor': 'Text Editor',
				'html': 'HTML',
				'aria_label': 'ARIA Label',
				'min_label': 'Min Label',
				'max_label': 'Max Label',
				'prefix': 'Präfix',
				'suffix': 'Suffix',
				'text': 'Button Text',
				'label_mask_row_prepend': 'Zeilen-Präfix',
				'label_mask_row_append': 'Zeilen-Suffix'
			};
			return labels[type] || type;
		},

		async scanForm(formId) {
			const btn = document.getElementById('wsform-ml-scan-btn');
			const resultContainer = document.getElementById('wsform-ml-scan-result');

			btn.classList.add('is-loading');
			btn.disabled = true;

			try {
				const response = await fetch(`${wsformML.restUrl}/forms/${formId}/scan`, {
					method: 'POST',
					headers: {
						'X-WP-Nonce': wsformML.nonce,
						'Content-Type': 'application/json'
					}
				});

				if (!response.ok) throw new Error('Scan failed');

				const result = await response.json();

				resultContainer.className = 'wsform-ml-notice success';
				resultContainer.innerHTML = `
					<strong>${wsformML.i18n.scanComplete}</strong><br>
					${result.stats.fields_found} Felder gefunden, 
					${result.stats.new_fields} neu, 
					${result.stats.updated_fields} aktualisiert, 
					${result.stats.deleted_fields} gelöscht
				`;
				resultContainer.style.display = 'block';

				await this.loadForms();
				await this.loadFormData(formId);

			} catch (error) {
				console.error('Scan error:', error);
				resultContainer.className = 'wsform-ml-notice error';
				resultContainer.textContent = wsformML.i18n.scanError + ': ' + error.message;
				resultContainer.style.display = 'block';
			} finally {
				btn.classList.remove('is-loading');
				btn.disabled = false;
			}
		},

		escapeHtml(text) {
			const div = document.createElement('div');
			div.textContent = text;
			return div.innerHTML;
		}
	};

	document.addEventListener('DOMContentLoaded', () => {
		WSFormML.init();

		const container = document.getElementById('wsform-ml-fields-container');

		// Event Delegation für alle Buttons
		container.addEventListener('click', async (e) => {
			// Toggle All Button
			if (e.target.closest('.wsform-ml-toggle-all')) {
				const btn = e.target.closest('.wsform-ml-toggle-all');
				const allBodies = container.querySelectorAll('.wsform-ml-field-body');
				const isExpanding = btn.textContent.includes('ausklappen');

				allBodies.forEach(body => {
					if (isExpanding) {
						body.classList.add('open');
					} else {
						body.classList.remove('open');
					}
				});

				if (isExpanding) {
					btn.innerHTML = '<span class="dashicons dashicons-editor-contract"></span> Alle einklappen';
				} else {
					btn.innerHTML = '<span class="dashicons dashicons-editor-expand"></span> Alle ausklappen';
				}
				return;
			}

			// Globaler Speicher-Button
			if (e.target.closest('.wsform-ml-save-all')) {
				const btn = e.target.closest('.wsform-ml-save-all');
				const textareas = document.querySelectorAll('.wsform-ml-translation-input');

				btn.classList.add('is-loading');
				btn.disabled = true;

				let saved = 0;
				let errors = 0;

				for (const textarea of textareas) {
					const data = {
						form_id: parseInt(textarea.dataset.formId),
						field_id: textarea.dataset.fieldId, // Nicht parseInt() - kann String sein!
						field_path: textarea.dataset.fieldPath,
						property_type: textarea.dataset.propertyType,
						language_code: WSFormML.currentLanguage,
						original_value: textarea.dataset.original,
						translated_value: textarea.value
					};

					try {
						const response = await fetch(`${wsformML.restUrl}/translations`, {
							method: 'POST',
							headers: {
								'X-WP-Nonce': wsformML.nonce,
								'Content-Type': 'application/json'
							},
							body: JSON.stringify(data)
						});

						if (response.ok) {
							saved++;
						} else {
							errors++;
						}
					} catch (error) {
						errors++;
					}
				}

				btn.innerHTML = `<span class="dashicons dashicons-arrow-down-alt"></span> ${saved} gespeichert${errors > 0 ? `, ${errors} Fehler` : ''}`;
				setTimeout(() => {
					btn.innerHTML = '<span class="dashicons dashicons-arrow-down-alt"></span> Alle Änderungen speichern';
				}, 3000);

				btn.classList.remove('is-loading');
				btn.disabled = false;
				return;
			}

			// Einzelne Save-Buttons entfernt - nur globaler Button wird verwendet
			if (false && e.target.classList.contains('wsform-ml-save-translation')) {
				const btn = e.target;
				const textarea = btn.previousElementSibling;

				const data = {
					form_id: parseInt(textarea.dataset.formId),
					field_id: parseInt(textarea.dataset.fieldId),
					field_path: textarea.dataset.fieldPath,
					property_type: textarea.dataset.propertyType,
					language_code: WSFormML.currentLanguage,
					original_value: textarea.dataset.original,
					translated_value: textarea.value
				};

				btn.classList.add('is-loading');
				btn.disabled = true;

				try {
					const response = await fetch(`${wsformML.restUrl}/translations`, {
						method: 'POST',
						headers: {
							'X-WP-Nonce': wsformML.nonce,
							'Content-Type': 'application/json'
						},
						body: JSON.stringify(data)
					});

					if (!response.ok) throw new Error('Save failed');

					btn.textContent = wsformML.i18n.saved;
					setTimeout(() => {
						btn.textContent = wsformML.i18n.saveTranslation;
					}, 2000);

					// Keine UI-Aktualisierung nötig - Übersetzung ist bereits gespeichert

				} catch (error) {
					console.error('Save error:', error);
					alert('Fehler beim Speichern: ' + error.message);
				} finally {
					btn.classList.remove('is-loading');
					btn.disabled = false;
				}
			}
		});
	});
})();
