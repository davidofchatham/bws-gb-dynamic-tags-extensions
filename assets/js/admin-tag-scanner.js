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
	var resultsWrap = document.getElementById( 'bws-scan-results' );
	var tbody       = document.getElementById( 'bws-results-tbody' );
	var selectAllCb = document.getElementById( 'bws-select-all' );
	var selectAllLbl = document.getElementById( 'bws-select-all-label' );
	var migrateSelBtn = document.getElementById( 'bws-migrate-selected-btn' );

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

					if ( scanResults.length === 0 ) {
						scanStatus.textContent = i18n.noIssues || 'No issues found.';
						return;
					}

					scanStatus.textContent = scanResults.length + ' post' + ( scanResults.length === 1 ? '' : 's' ) + ' found.';
					renderResults( scanResults );
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
	// RENDER RESULTS TABLE
	// ============================================================

	function renderResults( posts ) {
		tbody.innerHTML = '';
		selectAllCb.checked = false;
		updateMigrateBtn();

		posts.forEach( function ( post ) {
			var row  = document.createElement( 'tr' );
			row.setAttribute( 'data-post-id', post.post_id );

			// Issues list.
			var issueHtml = '<ul class="bws-issue-list">';
			( post.deprecated_tags || [] ).forEach( function ( t ) {
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
