/**
 * VITACARE CRM — bandeja (PR-5).
 * Consume vitacareCrm.restUrl / restNonce. Sin innerHTML de cuerpos de mensaje.
 */
(function () {
	'use strict';

	var cfg = window.vitacareCrm || {};
	var restUrl = (cfg.restUrl || '').replace(/\/$/, '');
	var nonce = cfg.restNonce || '';
	var pollMs = typeof cfg.pollInterval === 'number' ? cfg.pollInterval : 20000;
	var i18n = cfg.i18n || {};

	var state = {
		status: 'open',
		channel: '',
		q: '',
		items: [],
		selectedId: null,
		selected: null,
		messages: [],
		sending: false,
		pollTimer: null,
		searchTimer: null,
		attachment: null, // {ref, filename} tras subir vía /media/upload
		attaching: false,
	};

	var ATTACH_MAX_BYTES = 25 * 1024 * 1024;
	var ATTACH_CHANNELS = ['whatsapp', 'facebook'];

	var el = {};

	function t(key, fallback) {
		return i18n[key] || fallback || key;
	}

	function $(id) {
		return document.getElementById(id);
	}

	function api(path, options) {
		options = options || {};
		var headers = options.headers || {};
		headers['X-WP-Nonce'] = nonce;
		if (options.body && !headers['Content-Type']) {
			headers['Content-Type'] = 'application/json';
		}
		return fetch(restUrl + path, {
			method: options.method || 'GET',
			credentials: 'same-origin',
			headers: headers,
			body: options.body || undefined,
		}).then(function (res) {
			return res.json().then(function (data) {
				if (!res.ok) {
					var msg =
						(data && (data.message || (data.data && data.data.message))) ||
						t('errorGeneric', 'Error de red o permisos.');
					var err = new Error(msg);
					err.code = data && data.code;
					err.status = res.status;
					err.data = data;
					throw err;
				}
				return data;
			});
		});
	}

	function esc(s) {
		if (s == null) return '';
		return String(s);
	}

	function channelLabel(ch) {
		var map = {
			whatsapp: 'WhatsApp',
			facebook: 'Facebook',
			instagram: 'Instagram',
			email: t('email', 'Correo'),
		};
		return map[ch] || ch || '—';
	}

	function channelChipClass(ch) {
		var map = {
			whatsapp: 'vcrm-chip-wa',
			facebook: 'vcrm-chip-fb',
			instagram: 'vcrm-chip-ig',
			email: 'vcrm-chip-email',
		};
		return 'vcrm-chip ' + (map[ch] || '');
	}

	function formatTime(iso) {
		if (!iso) return '';
		var d = new Date(iso.indexOf('T') === -1 ? iso.replace(' ', 'T') : iso);
		if (isNaN(d.getTime())) return '';
		var now = new Date();
		var sameDay =
			d.getDate() === now.getDate() &&
			d.getMonth() === now.getMonth() &&
			d.getFullYear() === now.getFullYear();
		if (sameDay) {
			return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
		}
		return d.toLocaleDateString([], { day: '2-digit', month: 'short' });
	}

	function statusLabel(st) {
		var map = {
			open: t('statusOpen', 'Abierta'),
			pending: t('statusPending', 'Pendiente'),
			closed: t('statusClosed', 'Cerrada'),
		};
		return map[st] || st || '—';
	}

	function loadList() {
		var params = new URLSearchParams();
		params.set('status', state.status || 'open');
		params.set('per_page', '50');
		params.set('page', '1');
		if (state.channel) params.set('channel', state.channel);
		if (state.q) params.set('q', state.q);

		return api('/conversations?' + params.toString())
			.then(function (data) {
				state.items = (data && data.items) || [];
				renderList();
				updateMetricsFromList();
			})
			.catch(function (err) {
				el.convList.textContent = '';
				var div = document.createElement('div');
				div.className = 'vcrm-empty';
				div.textContent = err.message || t('errorLoad', 'No se pudo cargar la lista.');
				el.convList.appendChild(div);
			});
	}

	function updateMetricsFromList() {
		// Métricas de cabecera se mantienen del PHP; refresco ligero de total abierto vía re-count en items si status=open.
		if (state.status === 'open' && !state.channel && !state.q) {
			var totalEl = document.querySelector('[data-metric="total"]');
			if (totalEl) totalEl.textContent = String(state.items.length);
		}
	}

	function renderList() {
		el.convList.textContent = '';
		if (!state.items.length) {
			var empty = document.createElement('div');
			empty.className = 'vcrm-empty';
			empty.textContent = t('emptyList', 'No hay conversaciones con estos filtros.');
			el.convList.appendChild(empty);
			return;
		}

		state.items.forEach(function (item) {
			var btn = document.createElement('button');
			btn.type = 'button';
			btn.className =
				'vcrm-conv-item' + (state.selectedId === item.id ? ' is-active' : '');
			btn.setAttribute('role', 'option');
			btn.setAttribute('aria-selected', state.selectedId === item.id ? 'true' : 'false');
			btn.dataset.id = String(item.id);

			var top = document.createElement('div');
			top.className = 'vcrm-conv-item-top';
			var name = document.createElement('span');
			name.className = 'vcrm-conv-name';
			name.textContent =
				item.contact_name || item.contact_phone || item.external_contact_id || '#' + item.id;
			var time = document.createElement('span');
			time.className = 'vcrm-conv-time';
			time.textContent = formatTime(item.last_message_at);
			top.appendChild(name);
			top.appendChild(time);

			var preview = document.createElement('div');
			preview.className = 'vcrm-conv-preview';
			preview.textContent = item.last_message_preview || '—';

			var meta = document.createElement('div');
			meta.className = 'vcrm-conv-meta';
			var chip = document.createElement('span');
			chip.className = channelChipClass(item.channel);
			chip.textContent = channelLabel(item.channel);
			meta.appendChild(chip);
			if (item.unread_count > 0) {
				var badge = document.createElement('span');
				badge.className = 'vcrm-badge';
				badge.textContent = String(item.unread_count);
				meta.appendChild(badge);
			}

			btn.appendChild(top);
			btn.appendChild(preview);
			btn.appendChild(meta);
			btn.addEventListener('click', function () {
				selectConversation(item.id);
			});
			el.convList.appendChild(btn);
		});
	}

	function selectConversation(id) {
		state.selectedId = id;
		clearAttachment();
		renderList();
		loadThread(true);
		startPoll();
	}

	function loadThread(scrollBottom) {
		if (!state.selectedId) return Promise.resolve();

		return api('/conversations/' + state.selectedId)
			.then(function (conv) {
				state.selected = conv;
				renderContext(conv);
				renderThreadHeader(conv);
				loadVitacareContact(state.selectedId);
				return api(
					'/conversations/' + state.selectedId + '/messages?limit=50'
				);
			})
			.then(function (data) {
				state.messages = (data && data.items) || [];
				renderMessages(!!scrollBottom);
				// Marcar leído en lista local.
				state.items.forEach(function (it) {
					if (it.id === state.selectedId) it.unread_count = 0;
				});
				renderList();
			})
			.catch(function (err) {
				el.threadMessages.textContent = '';
				var div = document.createElement('div');
				div.className = 'vcrm-empty';
				div.textContent = err.message || t('errorThread', 'No se pudo cargar el hilo.');
				el.threadMessages.appendChild(div);
			});
	}

	function canSendToChannel(ch) {
		return ch === 'whatsapp' || ch === 'facebook' || ch === 'instagram' || ch === 'email';
	}

	function canAttachToChannel(ch) {
		return ATTACH_CHANNELS.indexOf(ch) !== -1;
	}

	function updateSendButton() {
		var ch = state.selected && state.selected.channel;
		var closed = state.selected && state.selected.status === 'closed';
		var hasContent = !!el.composerInput.value.trim() || !!state.attachment;
		el.btnSend.disabled =
			state.sending || state.attaching || !hasContent || !canSendToChannel(ch) || !!closed;
	}

	function renderThreadHeader(conv) {
		var name =
			conv.contact_name || conv.contact_phone || conv.external_contact_id || '#' + conv.id;
		el.threadName.textContent = name;
		el.threadChannel.hidden = false;
		el.threadChannel.className = channelChipClass(conv.channel);
		el.threadChannel.textContent = channelLabel(conv.channel);
		el.threadActions.hidden = false;
		el.composer.hidden = false;

		var isClosed = conv.status === 'closed';
		el.btnClose.hidden = isClosed;
		el.btnReopen.hidden = !isClosed;
		var canSend = !isClosed && canSendToChannel(conv.channel);
		el.composerInput.disabled = !canSend;
		if (el.attachBtn) {
			el.attachBtn.disabled = !canSend || !canAttachToChannel(conv.channel);
			if (!canAttachToChannel(conv.channel)) clearAttachment();
		}
		updateSendButton();
		if (conv.channel === 'whatsapp') {
			el.composerHint.textContent = t('hintWa', 'Solo texto · ventana 24 h de WhatsApp');
		} else if (conv.channel === 'facebook') {
			el.composerHint.textContent = t('hintFb', 'Messenger · ventana 24 h · solo texto');
		} else if (conv.channel === 'instagram') {
			el.composerHint.textContent = t('hintIg', 'Instagram Direct · ventana 24 h · solo texto');
		} else if (conv.channel === 'email') {
			el.composerHint.textContent = t('hintEmail', 'Respuesta por Gmail · texto');
		} else {
			el.composerHint.textContent = t('hintChannel', 'Envío no disponible para este canal todavía.');
		}
	}

	function renderContext(conv) {
		var map = {
			name: conv.contact_name || '—',
			phone: conv.contact_phone || '—',
			channel: channelLabel(conv.channel),
			status: statusLabel(conv.status),
			assigned: conv.assigned_to ? String(conv.assigned_to) : '—',
			external: conv.external_contact_id || '—',
		};
		Object.keys(map).forEach(function (k) {
			var node = el.contextDl.querySelector('[data-field="' + k + '"]');
			if (node) node.textContent = map[k];
		});
	}

	function formatMoneyMinor(minor) {
		var amount = (Number(minor) || 0) / 100;
		return '$' + amount.toFixed(2);
	}

	function appointmentStatusLabel(st) {
		var map = {
			scheduled: t('apptScheduled', 'Agendada'),
			in_progress: t('apptInProgress', 'En curso'),
			completed: t('apptCompleted', 'Completada'),
			cancelled: t('apptCancelled', 'Cancelada'),
		};
		return map[st] || st || '—';
	}

	function loadVitacareContact(id) {
		if (!el.vitacareContact) return;
		el.vitacareContact.hidden = true;
		if (el.vitacareStatus) {
			el.vitacareStatus.hidden = false;
			el.vitacareStatus.textContent = t(
				'vitacareLooking',
				'Buscando coincidencia con un usuario de VITACARE…'
			);
		}
		api('/conversations/' + id + '/vitacare-contact')
			.then(function (data) {
				if (state.selectedId !== id) return; // el usuario ya cambió de hilo
				if (data && data.matched) {
					renderVitacareContact(data);
				} else {
					renderVitacareNoMatch();
				}
			})
			.catch(function () {
				if (state.selectedId !== id) return;
				if (el.vitacareStatus) {
					el.vitacareStatus.hidden = false;
					el.vitacareStatus.textContent = t(
						'vitacareError',
						'No se pudo consultar VITACARE.'
					);
				}
			});
	}

	function renderVitacareNoMatch() {
		if (el.vitacareStatus) {
			el.vitacareStatus.hidden = false;
			el.vitacareStatus.textContent = t(
				'vitacareNoMatch',
				'Sin coincidencia con un usuario registrado de VITACARE.'
			);
		}
	}

	function renderVitacareContact(data) {
		if (el.vitacareStatus) el.vitacareStatus.hidden = true;
		if (!el.vitacareContact) return;
		el.vitacareContact.hidden = false;

		var map = {
			name: data.name || '—',
			email: data.email || '—',
			roles: Array.isArray(data.roles) && data.roles.length ? data.roles.join(', ') : '—',
			membership:
				data.membership && data.membership.name
					? data.membership.name + ' (' + statusLabel(data.membership.status) + ')'
					: t('vitacareNoMembership', 'Sin membresía activa'),
			pending: formatMoneyMinor(data.pending_payments_minor),
		};
		Object.keys(map).forEach(function (k) {
			var node = el.vitacareDl.querySelector('[data-vf="' + k + '"]');
			if (node) node.textContent = map[k];
		});

		el.vitacareAppointments.textContent = '';
		var appointments = Array.isArray(data.appointments) ? data.appointments : [];
		if (!appointments.length) {
			var empty = document.createElement('p');
			empty.className = 'vcrm-muted';
			empty.textContent = t('vitacareNoAppointments', 'Sin citas registradas.');
			el.vitacareAppointments.appendChild(empty);
			return;
		}
		var title = document.createElement('div');
		title.className = 'vcrm-muted';
		title.textContent = t('vitacareRecentAppointments', 'Citas recientes:');
		el.vitacareAppointments.appendChild(title);
		appointments.forEach(function (a) {
			var row = document.createElement('div');
			row.className = 'vcrm-vitacare-appt';
			row.textContent =
				(a.service_code || '—') +
				' · ' +
				formatTime(a.scheduled_at) +
				' · ' +
				appointmentStatusLabel(a.status);
			el.vitacareAppointments.appendChild(row);
		});
	}

	function renderMedia(m) {
		var wrap = document.createElement('div');
		wrap.className = 'vcrm-bubble-media';
		var type = m.message_type;

		if (type === 'image' || type === 'sticker') {
			var link = document.createElement('a');
			link.href = m.media_url;
			link.target = '_blank';
			link.rel = 'noopener noreferrer';
			var img = document.createElement('img');
			img.src = m.media_url;
			img.alt = type === 'sticker' ? 'sticker' : t('image', 'Imagen');
			img.loading = 'lazy';
			img.className = 'vcrm-media-img';
			link.appendChild(img);
			wrap.appendChild(link);
		} else if (type === 'audio') {
			var audio = document.createElement('audio');
			audio.controls = true;
			audio.preload = 'none';
			audio.src = m.media_url;
			audio.className = 'vcrm-media-audio';
			wrap.appendChild(audio);
		} else if (type === 'video') {
			var video = document.createElement('video');
			video.controls = true;
			video.preload = 'none';
			video.src = m.media_url;
			video.className = 'vcrm-media-video';
			wrap.appendChild(video);
		} else {
			var a = document.createElement('a');
			a.href = m.media_url;
			a.target = '_blank';
			a.rel = 'noopener noreferrer';
			a.className = 'vcrm-media-file';
			a.textContent = t('downloadFile', 'Descargar archivo');
			wrap.appendChild(a);
		}

		return wrap;
	}

	function renderMessages(scrollBottom) {
		el.threadMessages.textContent = '';
		if (!state.messages.length) {
			var empty = document.createElement('div');
			empty.className = 'vcrm-empty';
			empty.textContent = t('emptyThread', 'Sin mensajes aún.');
			el.threadMessages.appendChild(empty);
			return;
		}

		state.messages.forEach(function (m) {
			var bubble = document.createElement('div');
			var isOut = m.direction === 'outbound';
			bubble.className = 'vcrm-bubble ' + (isOut ? 'vcrm-bubble-out' : 'vcrm-bubble-in');

			if (m.media_url) {
				bubble.appendChild(renderMedia(m));
			}

			var bodyText = m.body != null && m.body !== '' ? String(m.body) : '';
			var isPlaceholder = /^\[.*\]$/.test(bodyText);
			if (bodyText && !(m.media_url && isPlaceholder)) {
				var body = document.createElement('div');
				body.className = 'vcrm-bubble-body';
				body.textContent = bodyText;
				bubble.appendChild(body);
			} else if (!bodyText && !m.media_url) {
				var fallback = document.createElement('div');
				fallback.className = 'vcrm-bubble-body';
				fallback.textContent = '[' + (m.message_type || 'msg') + ']';
				bubble.appendChild(fallback);
			}

			var meta = document.createElement('span');
			meta.className = 'vcrm-bubble-meta';
			var bits = [formatTime(m.created_at)];
			if (isOut) {
				bits.push(m.sender_type === 'staff' ? t('fromStaff', 'CRM') : t('fromApp', 'App'));
				if (m.delivery_status) bits.push(m.delivery_status);
			}
			meta.textContent = bits.filter(Boolean).join(' · ');

			bubble.appendChild(meta);
			el.threadMessages.appendChild(bubble);
		});

		if (scrollBottom) {
			el.threadMessages.scrollTop = el.threadMessages.scrollHeight;
		}
	}

	function renderAttachChip() {
		if (!el.attachChip) return;
		if (state.attaching) {
			el.attachChip.hidden = false;
			el.attachChipName.textContent = t('uploading', 'Subiendo…');
			el.attachRemove.hidden = true;
			return;
		}
		if (!state.attachment) {
			el.attachChip.hidden = true;
			return;
		}
		el.attachChip.hidden = false;
		el.attachChipName.textContent = state.attachment.filename || t('attachment', 'Adjunto');
		el.attachRemove.hidden = false;
	}

	function clearAttachment() {
		state.attachment = null;
		state.attaching = false;
		if (el.attachInput) el.attachInput.value = '';
		renderAttachChip();
		updateSendButton();
	}

	function uploadAttachment(file) {
		if (!file) return;
		if (file.size > ATTACH_MAX_BYTES) {
			showComposerError(t('attachTooBig', 'El archivo supera el máximo de 25 MB.'));
			if (el.attachInput) el.attachInput.value = '';
			return;
		}

		state.attaching = true;
		state.attachment = null;
		renderAttachChip();
		updateSendButton();
		showComposerError('');

		var fd = new FormData();
		fd.append('file', file);

		fetch(restUrl + '/media/upload', {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'X-WP-Nonce': nonce },
			body: fd,
		})
			.then(function (res) {
				return res.json().then(function (data) {
					if (!res.ok) {
						var msg =
							(data && (data.message || (data.data && data.data.message))) ||
							t('errorGeneric', 'Error de red o permisos.');
						throw new Error(msg);
					}
					return data;
				});
			})
			.then(function (data) {
				state.attachment = {
					ref: data.media_attachment_id,
					filename: data.filename || file.name,
				};
			})
			.catch(function (err) {
				showComposerError(err.message);
				if (el.attachInput) el.attachInput.value = '';
			})
			.finally(function () {
				state.attaching = false;
				renderAttachChip();
				updateSendButton();
			});
	}

	function showComposerError(msg) {
		if (!msg) {
			el.composerError.hidden = true;
			el.composerError.textContent = '';
			return;
		}
		el.composerError.hidden = false;
		el.composerError.textContent = msg;
	}

	function sendMessage() {
		if (state.sending || state.attaching || !state.selectedId) return;
		var text = el.composerInput.value.trim();
		var attachment = state.attachment;
		if (!text && !attachment) return;

		state.sending = true;
		el.btnSend.disabled = true;
		showComposerError('');

		var payload = {};
		if (text) payload.body = text;
		if (attachment) payload.media_attachment_id = attachment.ref;

		api('/conversations/' + state.selectedId + '/messages', {
			method: 'POST',
			body: JSON.stringify(payload),
		})
			.then(function (msg) {
				el.composerInput.value = '';
				clearAttachment();
				if (msg && msg.id) {
					state.messages.push(msg);
					renderMessages(true);
				} else {
					return loadThread(true);
				}
				// Actualizar preview en lista.
				state.items.forEach(function (it) {
					if (it.id === state.selectedId) {
						it.last_message_preview = text || t('attachment', 'Adjunto');
						it.last_message_at = msg.created_at || it.last_message_at;
					}
				});
				renderList();
			})
			.catch(function (err) {
				var m = err.message;
				if (err.code === 'vitacare_crm_outside_window') {
					m = t(
						'outsideWindow',
						'Fuera de la ventana de 24 horas. El cliente debe escribir primero.'
					);
				}
				showComposerError(m);
			})
			.finally(function () {
				state.sending = false;
				updateSendButton();
			});
	}

	function patchStatus(status) {
		if (!state.selectedId) return;
		api('/conversations/' + state.selectedId, {
			method: 'PATCH',
			body: JSON.stringify({ status: status }),
		})
			.then(function (conv) {
				state.selected = conv;
				renderThreadHeader(conv);
				renderContext(conv);
				loadList();
			})
			.catch(function (err) {
				showComposerError(err.message);
			});
	}

	function startPoll() {
		stopPoll();
		state.pollTimer = setInterval(function () {
			loadList();
			if (state.selectedId) {
				loadThread(false);
			}
		}, pollMs);
	}

	function stopPoll() {
		if (state.pollTimer) {
			clearInterval(state.pollTimer);
			state.pollTimer = null;
		}
	}

	function bind() {
		el.convList = $('vcrm-conv-list');
		el.threadMessages = $('vcrm-thread-messages');
		el.threadName = $('vcrm-thread-name');
		el.threadChannel = $('vcrm-thread-channel');
		el.threadActions = $('vcrm-thread-actions');
		el.composer = $('vcrm-composer');
		el.composerInput = $('vcrm-composer-input');
		el.composerError = $('vcrm-composer-error');
		el.composerHint = $('vcrm-composer-hint');
		el.btnSend = $('vcrm-btn-send');
		el.btnClose = $('vcrm-btn-close');
		el.btnReopen = $('vcrm-btn-reopen');
		el.contextDl = $('vcrm-context-dl');
		el.vitacareContact = $('vcrm-vitacare-contact');
		el.vitacareDl = $('vcrm-vitacare-dl');
		el.vitacareAppointments = $('vcrm-vitacare-appointments');
		el.vitacareStatus = $('vcrm-vitacare-status');
		el.attachBtn = $('vcrm-btn-attach');
		el.attachInput = $('vcrm-attach-input');
		el.attachChip = $('vcrm-attach-chip');
		el.attachChipName = $('vcrm-attach-chip-name');
		el.attachRemove = $('vcrm-attach-remove');
		el.filterStatus = $('vcrm-filter-status');
		el.filterChannel = $('vcrm-filter-channel');
		el.filterQ = $('vcrm-filter-q');
		el.refresh = $('vcrm-refresh');

		if (!el.convList || !restUrl || !nonce) {
			if (el.convList) {
				el.convList.textContent = '';
				var d = document.createElement('div');
				d.className = 'vcrm-empty';
				d.textContent = t('noConfig', 'CRM no configurado (REST).');
				el.convList.appendChild(d);
			}
			return;
		}

		el.filterStatus.addEventListener('change', function () {
			state.status = el.filterStatus.value;
			loadList();
		});
		el.filterChannel.addEventListener('change', function () {
			state.channel = el.filterChannel.value;
			loadList();
		});
		el.filterQ.addEventListener('input', function () {
			clearTimeout(state.searchTimer);
			state.searchTimer = setTimeout(function () {
				state.q = el.filterQ.value.trim();
				loadList();
			}, 350);
		});
		el.refresh.addEventListener('click', function () {
			loadList();
			if (state.selectedId) loadThread(false);
		});
		el.btnSend.addEventListener('click', sendMessage);
		el.composerInput.addEventListener('input', updateSendButton);
		if (el.attachBtn && el.attachInput) {
			el.attachBtn.addEventListener('click', function () {
				el.attachInput.click();
			});
			el.attachInput.addEventListener('change', function () {
				var file = el.attachInput.files && el.attachInput.files[0];
				uploadAttachment(file);
			});
		}
		if (el.attachRemove) {
			el.attachRemove.addEventListener('click', clearAttachment);
		}
		el.composerInput.addEventListener('keydown', function (e) {
			if (e.key === 'Enter' && !e.shiftKey) {
				e.preventDefault();
				sendMessage();
			}
		});
		el.btnClose.addEventListener('click', function () {
			patchStatus('closed');
		});
		el.btnReopen.addEventListener('click', function () {
			patchStatus('open');
		});

		document.addEventListener('visibilitychange', function () {
			if (document.hidden) stopPoll();
			else startPoll();
		});

		loadList().then(function () {
			startPoll();
			// Deep link (?c=ID) desde enlaces externos, p. ej. "Ver hilo" en
			// CRM VITACARE → Leads (D-23 Fase 2).
			var params = new URLSearchParams(window.location.search);
			var deepLinkId = parseInt(params.get('c'), 10);
			if (deepLinkId > 0) {
				selectConversation(deepLinkId);
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', bind);
	} else {
		bind();
	}
})();
