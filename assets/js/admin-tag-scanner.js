/**
 * Admin Tag Migration Scanner.
 *
 * Handles the Migration Tool section on the BWS Tag Extensions settings page:
 *   - Scan button: queries all posts for deprecated tags / option issues.
 *   - Per-post Migrate button: migrates a single post (with revision when supported).
 *   - Bulk Migrate Selected: paginated AJAX batch, progress bar.
 *
 * Expects window.bwsTagScanner localized by SettingsPage::enqueue_scripts().
 *
 * @package BWS_Dynamic_Tags
 * @since 1.6.0
 */
( function () {
	'use strict';

	var cfg       = window.bwsTagScanner || {};
	var ajaxUrl   = cfg.ajaxUrl   || '';
	var nonce     = cfg.nonce     || '';
	var batchSize = cfg.batchSize || 10;
	var i18n      = cfg.i18n     || {};

	// Scan results: array of post objects returned by PHP.
	var scanResults = [];

	// ============================================================
	// UTILITY
	// ============================================================

	function esc( str ) {
		return String( str )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' );
	}

	/**
	 * Refresh the persisted pattern-cache line from an AJAX response (#99).
	 *
	 * The line is PHP-rendered at page load and goes stale the moment a scan or a migrate
	 * reconciles, because neither reloads the page. Both handlers return the freshly
	 * formatted line so the number on screen is the number from the run that just ran.
	 *
	 * PHP owns the wording; this only places it. Formatting it here would put a second copy
	 * of the three-branch rule in a second language, which is the drift the shared formatter
	 * exists to prevent.
	 */
	function setPatternCacheLine( data ) {
		if ( ! patternCacheEl || ! data || typeof data.patternCacheLine !== 'string' ) {
			return;
		}
		if ( data.patternCacheLine === '' ) {
			return;
		}
		patternCacheEl.textContent = data.patternCacheLine;
	}

	function post( action, data, onSuccess, onError ) {
		var body = new FormData();
		body.append( 'action', action );
		body.append( 'nonce',  nonce );
		Object.keys( data ).forEach( function ( k ) {
			body.append( k, typeof data[ k ] === 'object' ? JSON.stringify( data[ k ] ) : data[ k ] );
		} );

		fetch( ajaxUrl, { method: 'POST', body: body } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( json ) {
				if ( json.success ) {
					onSuccess( json.data );
				} else {
					onError( ( json.data && json.data.message ) || 'Unknown error' );
				}
			} )
			.catch( function ( err ) { onError( String( err ) ); } );
	}

	// ============================================================
	// SCAN
	// ============================================================

	var scanBtn    = document.getElementById( 'bws-scan-btn' );
	var scanStatus = document.getElementById( 'bws-scan-status' );
	var patternCacheEl = document.getElementById( 'bws-pattern-cache-status' );
	var resultsWrap = document.getElementById( 'bws-scan-results' );
	var tbody       = document.getElementById( 'bws-results-tbody' );
	var selectAllCb = document.getElementById( 'bws-select-all' );
	var selectAllLbl = document.getElementById( 'bws-select-all-label' );
	var migrateSelBtn = document.getElementById( 'bws-migrate-selected-btn' );

	// The two non-conversion channels and the exemption disclosure (FW-39, D46).
	var declinedWrap = document.getElementById( 'bws-scan-declined' );
	var declinedList = document.getElementById( 'bws-declined-list' );
	var skippedWrap  = document.getElementById( 'bws-scan-skipped' );
	var skippedList  = document.getElementById( 'bws-skipped-list' );
	var exemptWrap   = document.getElementById( 'bws-scan-exemption' );
	var exemptLine   = document.getElementById( 'bws-scan-exemption-line' );

	/**
	 * Whether a scan row has anything the Migrate button would actually do.
	 *
	 * A deprecated tag counts only when its verdict is `convert`; an option migration always
	 * counts, because those never reach either channel. `status` is absent on a response from
	 * a pre-1.20.0 build, and an absent verdict reads as convertible — which is exactly what
	 * that build meant by listing the row.
	 */
	function hasConvertibleWork( post ) {
		var tags = post.deprecated_tags || [];
		var convertible = tags.some( function ( t ) {
			return ! t.status || t.status === 'convert';
		} );

		return convertible || ( post.option_migrations || [] ).length > 0;
	}

	if ( scanBtn ) {
		scanBtn.addEventListener( 'click', function () {
			scanBtn.disabled = true;
			scanStatus.textContent = i18n.scanning || 'Scanning…';
			resultsWrap.style.display = 'none';
			tbody.innerHTML = '';
			scanResults = [];

			post(
				'bws_scan_tags',
				{},
				function ( data ) {
					scanBtn.disabled = false;
					scanResults = data.posts || [];

					// BEFORE the zero-result return, not after it (#99). A scan also
					// reconciles the pattern cache, and that reconcile is content-agnostic:
					// a run can repair five patterns while finding zero posts, which is
					// precisely the already-converted site this repair exists for. Leaving
					// this below the return printed "No deprecated tags found." and stopped,
					// so the one run that did real work reported none of it.
					setPatternCacheLine( data );

					// THE THREE CHANNELS RENDER BEFORE THE ZERO-RESULT RETURN, for the same
					// reason the pattern-cache line does: a site whose every finding is a
					// skip or a decline has results worth printing and no convertible post,
					// and leaving this below the return would report "no issues" over a list
					// of tags we deliberately left alone.
					renderChannels( data.channels || {} );

					if ( scanResults.length === 0 ) {
						scanStatus.textContent = i18n.noIssues || 'No issues found.';
						return;
					}

					// ONLY POSTS WITH SOMETHING TO CONVERT REACH THE TABLE. The table carries a
					// Migrate button per row, so a post whose every finding was declined or
					// skipped would offer work that cannot happen — the run would report "no
					// changes needed" and the owner would have no idea why. Those posts are
					// accounted for in the two channels below instead.
					var convertible = scanResults.filter( hasConvertibleWork );

					if ( convertible.length === 0 ) {
						scanStatus.textContent = i18n.nothingToConvert || 'Nothing to convert.';
						return;
					}

					scanStatus.textContent = convertible.length + ' post' + ( convertible.length === 1 ? '' : 's' ) + ' found.';
					renderResults( convertible );
					resultsWrap.style.display = 'block';
				},
				function ( err ) {
					scanBtn.disabled = false;
					scanStatus.textContent = ( i18n.errorPrefix || 'Error:' ) + ' ' + err;
				}
			);
		} );
	}

	// ============================================================
	// RENDER THE TWO NON-CONVERSION CHANNELS (FW-39, D46)
	// ============================================================

	/**
	 * Place the declined list, the skipped list and the exemption line.
	 *
	 * EVERY SENTENCE ARRIVES COMPOSED. PHP owns each channel's vocabulary, keyed by reason
	 * and censused against its own enum, so a reason added without wording fails a harness
	 * instead of reaching here and printing an empty row. This function decides layout and
	 * nothing else — the one string it authors is the preview's label.
	 *
	 * A DECLINE GETS A CLAIM CONTROL; A SKIP GETS NOTHING. That asymmetry is the channels'
	 * whole point: a decline gates a rewrite and has an author action, a skip has neither.
	 */
	function renderChannels( channels ) {
		var declined = channels.declined || [];
		var skipped  = channels.skipped  || [];
		var optedIn  = channels.optedIn  || [];

		if ( exemptWrap && exemptLine ) {
			exemptLine.textContent = channels.exemptLine || '';
			exemptWrap.style.display = channels.exemptLine ? 'block' : 'none';
		}

		if ( declinedList && declinedWrap ) {
			declinedList.innerHTML = '';
			declined.forEach( function ( row ) {
				declinedList.appendChild( declinedRow( row, optedIn.indexOf( row.tag ) !== -1 ) );
			} );
			declinedWrap.style.display = declined.length ? 'block' : 'none';
		}

		if ( skippedList && skippedWrap ) {
			skippedList.innerHTML = '';
			skipped.forEach( function ( row ) {
				var li = document.createElement( 'li' );
				li.className = 'bws-channel-item bws-channel-skipped';
				li.innerHTML = '<p class="bws-channel-line">' + esc( row.line ) + '</p>' + previewHtml( row );
				skippedList.appendChild( li );
			} );
			skippedWrap.style.display = skipped.length ? 'block' : 'none';
		}
	}

	/** The stored example, so a count is something an owner can recognize (D37). */
	function previewHtml( row ) {
		if ( ! row.sample ) { return ''; }

		return '<p class="bws-channel-preview">' +
			esc( i18n.storedExample || 'Stored example:' ) + ' <code>' + esc( row.sample ) + '</code>' +
			'</p>';
	}

	function declinedRow( row, claimed ) {
		var li = document.createElement( 'li' );
		li.className = 'bws-channel-item bws-channel-declined';

		var action = row.action
			? '<p class="bws-channel-action">' + esc( row.action ) + '</p>'
			: '';

		li.innerHTML =
			'<p class="bws-channel-line">' + esc( row.line ) + '</p>' +
			action +
			previewHtml( row ) +
			'<p class="bws-channel-claim"><label>' +
				'<input type="checkbox" class="bws-claim-cb"' + ( claimed ? ' checked' : '' ) + ' />' +
				' ' + esc( i18n.claimLabel || 'These tags are mine, convert them' ) +
			'</label> <span class="bws-claim-status" aria-live="polite"></span></p>';

		var cb     = li.querySelector( '.bws-claim-cb' );
		var status = li.querySelector( '.bws-claim-status' );

		// THE BOX REVERTS ON FAILURE rather than staying where the click left it. It is the
		// control that lifts the ownership guard, so a box showing "claimed" over an option
		// that was never written would read as a decision the site never recorded.
		cb.addEventListener( 'change', function () {
			var wanted = cb.checked;
			cb.disabled = true;
			status.textContent = i18n.saving || 'Saving…';

			post(
				'bws_ownership_optin',
				{ tag: row.tag, claim: wanted ? '1' : '0' },
				function () {
					cb.disabled = false;
					status.textContent = i18n.claimSaved || 'Saved. Scan again to pick up the change.';
				},
				function ( err ) {
					cb.checked  = ! wanted;
					cb.disabled = false;
					status.textContent = ( i18n.errorPrefix || 'Error:' ) + ' ' + err;
				}
			);
		} );

		return li;
	}

	// ============================================================
	// RENDER RESULTS TABLE
	// ============================================================

	function renderResults( posts ) {
		tbody.innerHTML = '';
		selectAllCb.checked = false;
		updateMigrateBtn();

		posts.forEach( function ( post ) {
			var row  = document.createElement( 'tr' );
			row.setAttribute( 'data-post-id', post.post_id );

			// Issues list — ONLY WHAT THIS ROW'S MIGRATE BUTTON WILL CONVERT. A declined or
			// skipped tag in the same post is reported in its own channel, with its own
			// reason; listing it here too would name it under a heading that says it is
			// about to change, and leave an owner comparing two accounts of one tag.
			var issueHtml = '<ul class="bws-issue-list">';
			( post.deprecated_tags || [] ).forEach( function ( t ) {
				if ( t.status && t.status !== 'convert' ) { return; }
				issueHtml += '<li class="bws-issue-tag">⚠ <code>' + esc( t.tag ) + '</code>';
				if ( ! t.has_migration ) { issueHtml += ' <em>(no auto-convert)</em>'; }
				issueHtml += '</li>';
			} );
			( post.option_migrations || [] ).forEach( function ( m ) {
				issueHtml += '<li class="bws-issue-opt">⚙ ' + esc( m.label ) + '</li>';
			} );
			issueHtml += '</ul>';

			// No-revision warning.
			var revWarn = '';
			if ( ! post.has_revision_support ) {
				revWarn = '<div class="bws-no-revision">' + esc( i18n.noRevision || '⚠ No undo' ) + '</div>';
			}

			row.innerHTML =
				'<td class="bws-cb-col"><input type="checkbox" class="bws-row-cb" value="' + esc( post.post_id ) + '" /></td>' +
				'<td><a href="' + esc( post.edit_url ) + '" target="_blank" rel="noopener">' + esc( post.post_title ) + '</a>' + revWarn + '</td>' +
				'<td><code>' + esc( post.post_type ) + '</code></td>' +
				'<td>' + issueHtml + '</td>' +
				'<td>' +
					'<button type="button" class="button bws-migrate-one-btn" data-post-id="' + esc( post.post_id ) + '">' +
						'Migrate' +
					'</button>' +
					'<span class="bws-row-status"></span>' +
				'</td>';

			tbody.appendChild( row );

			// Per-row migrate button.
			row.querySelector( '.bws-migrate-one-btn' ).addEventListener( 'click', function () {
				migratePostIds( [ post.post_id ], [ row ] );
			} );

			// Track checkbox for select-all / migrate-selected.
			row.querySelector( '.bws-row-cb' ).addEventListener( 'change', updateMigrateBtn );
		} );
	}

	// ============================================================
	// SELECT ALL
	// ============================================================

	if ( selectAllCb ) {
		selectAllCb.addEventListener( 'change', function () {
			var all = tbody.querySelectorAll( '.bws-row-cb' );
			all.forEach( function ( cb ) { cb.checked = selectAllCb.checked; } );
			selectAllLbl.textContent = selectAllCb.checked
				? ( i18n.deselectAll || 'Deselect all' )
				: ( i18n.selectAll   || 'Select all' );
			updateMigrateBtn();
		} );
	}

	function updateMigrateBtn() {
		var checked = tbody.querySelectorAll( '.bws-row-cb:checked' ).length;
		if ( migrateSelBtn ) {
			migrateSelBtn.disabled = ( checked === 0 );
			migrateSelBtn.textContent = checked > 0
				? ( ( i18n.migrateAll || 'Migrate Selected' ) + ' (' + checked + ')' )
				: ( i18n.migrateAll || 'Migrate Selected' );
		}
	}

	// ============================================================
	// BULK MIGRATE SELECTED
	// ============================================================

	if ( migrateSelBtn ) {
		migrateSelBtn.addEventListener( 'click', function () {
			var checkedCbs = Array.from( tbody.querySelectorAll( '.bws-row-cb:checked' ) );
			if ( ! checkedCbs.length ) { return; }

			var ids  = checkedCbs.map( function ( cb ) { return parseInt( cb.value, 10 ); } );
			var rows = checkedCbs.map( function ( cb ) { return cb.closest( 'tr' ); } );

			migratePostIds( ids, rows );
		} );
	}

	// ============================================================
	// MIGRATE ENGINE (shared by per-post and bulk)
	// ============================================================

	var progressWrap  = document.getElementById( 'bws-progress-wrap' );
	var progressFill  = document.getElementById( 'bws-progress-fill' );
	var progressLabel = document.getElementById( 'bws-progress-label' );

	function migratePostIds( ids, rows ) {
		var total     = ids.length;
		var processed = 0;

		// Build a map for fast row lookup.
		var rowMap = {};
		rows.forEach( function ( row ) {
			rowMap[ row.getAttribute( 'data-post-id' ) ] = row;
		} );

		// Disable controls during migration.
		setMigrating( true );
		showProgress( 0, total );

		function processBatch( offset ) {
			var batch    = ids.slice( offset, offset + batchSize );
			var batchRows = batch.map( function ( id ) { return rowMap[ id ]; } );
			var isFinal   = offset + batchSize >= total;

			batchRows.forEach( function ( row ) {
				if ( row ) {
					var btn = row.querySelector( '.bws-migrate-one-btn' );
					var st  = row.querySelector( '.bws-row-status' );
					if ( btn ) { btn.disabled = true; }
					if ( st  ) { st.textContent = '…'; st.className = 'bws-row-status'; }
				}
			} );

			post(
				'bws_migrate_tags',
				{ post_ids: batch, is_final: isFinal ? '1' : '0' },
				function ( data ) {
					( data.results || [] ).forEach( function ( result ) {
						var row = rowMap[ result.post_id ];
						if ( ! row ) { return; }

						var st  = row.querySelector( '.bws-row-status' );
						var btn = row.querySelector( '.bws-migrate-one-btn' );
						if ( btn ) { btn.disabled = true; }

						if ( st ) {
							if ( result.changed ) {
								var parts = [];
								if ( result.tag_count > 0 )    { parts.push( result.tag_count + ' ' + ( i18n.tagsMigrated || 'tags migrated' ) ); }
								if ( result.option_count > 0 ) { parts.push( result.option_count + ' ' + ( i18n.optsMigrated || 'option fixes applied' ) ); }
								if ( ! result.has_revision )   { parts.push( '⚠ no revision' ); }
								st.textContent = '✓ ' + ( parts.length ? parts.join( ', ' ) : i18n.done || 'Done' );
								st.className = 'bws-row-status ok';
							} else {
								st.textContent = i18n.noChange || 'No changes needed';
								st.className = 'bws-row-status';
							}
						}
					} );

					processed += data.processed || batch.length;
					showProgress( processed, total );

					if ( offset + batchSize < total ) {
						processBatch( offset + batchSize );
					} else {
						setMigrating( false );
						scanStatus.textContent = i18n.bulkDone
							? i18n.bulkDone.replace( '%d', processed )
							: processed + ' posts processed.';
						// Only the final batch reconciles, so only it carries a line.
						setPatternCacheLine( data );
					}
				},
				function ( err ) {
					setMigrating( false );
					scanStatus.textContent = ( i18n.errorPrefix || 'Error:' ) + ' ' + err;
				}
			);
		}

		processBatch( 0 );
	}

	function showProgress( done, total ) {
		if ( ! progressWrap ) { return; }
		var pct = total > 0 ? Math.round( ( done / total ) * 100 ) : 0;
		progressWrap.style.display = 'flex';
		if ( progressFill )  { progressFill.style.width = pct + '%'; }
		if ( progressLabel ) {
			progressLabel.textContent = done + ' / ' + total;
		}
	}

	function setMigrating( active ) {
		if ( scanBtn )        { scanBtn.disabled = active; }
		if ( migrateSelBtn )  { migrateSelBtn.disabled = active; }
		if ( selectAllCb )    { selectAllCb.disabled = active; }
		if ( progressWrap && ! active ) { progressWrap.style.display = 'none'; }
	}
} )();
