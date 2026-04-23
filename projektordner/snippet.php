<?php
/**
 * Plugin Name: Umami Stats Viewer (Secured)
 * Plugin URI: https://onyva.xyz
 * Description: Zeigt Umami Analytics Statistiken via API im WordPress Dashboard
 * Version: 2.0.1
 * Author: OnYva
 * License: GPL v2 or later
 * Text Domain: umami-stats-viewer
 */

// Direkten Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

class Umami_Stats_Viewer {

    private $api_url = 'https://cockpit.digital-barrierefrei.ch/api';

    /**
     * Verschlüsselt ein Passwort mit zufälligem IV
     */
    private function encrypt_password($password) {
        if (empty($password)) {
            return '';
        }
        
        $key = wp_salt('auth');
        // Zufälligen IV generieren
        $iv = openssl_random_pseudo_bytes(16);
        
        $encrypted = openssl_encrypt($password, 'AES-256-CBC', $key, 0, $iv);
        
        // IV vorne anhängen für späteres Entschlüsseln
        return base64_encode($iv . $encrypted);
    }

    /**
     * Entschlüsselt ein Passwort
     */
    private function decrypt_password($encrypted) {
        if (empty($encrypted)) {
            return '';
        }
        
        $key = wp_salt('auth');
        $data = base64_decode($encrypted);
        
        // Die ersten 16 Bytes sind der IV
        $iv = substr($data, 0, 16);
        $encrypted_data = substr($data, 16);
        
        return openssl_decrypt($encrypted_data, 'AES-256-CBC', $key, 0, $iv);
    }

    public function __construct() {
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_menu', array($this, 'add_analytics_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widget'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_styles'));
        add_action('wp_ajax_umami_get_stats', array($this, 'ajax_get_stats'));
    }

    /**
     * Fügt Analytics Menüpunkt hinzu
     */
    public function add_analytics_page() {
        if (!$this->user_can_view_stats()) {
            return;
        }

        add_menu_page(
            'Analytics',
            'Analytics',
            'read',
            'umami-analytics',
            array($this, 'display_analytics_page'),
            'dashicons-chart-area',
            3
        );
    }

    /**
     * Zeigt die Analytics Seite mit API-Daten
     */
    public function display_analytics_page() {
        $username = get_option('umami_username', '');
        $password = get_option('umami_password', '');
        $website_id = get_option('umami_website_id', '');
        $settings_url = admin_url('options-general.php?page=umami-stats-settings');

        if (empty($username) || empty($password) || empty($website_id)) {
            ?>
            <div class="wrap umami-analytics-page">
                <div class="umami-analytics-setup">
                    <span class="dashicons dashicons-chart-area umami-icon-xlarge"></span>
                    <h2>Analytics noch nicht konfiguriert</h2>
                    <p>Bitte trage deine Umami API-Zugangsdaten in den Einstellungen ein.</p>
                    <a href="<?php echo esc_url($settings_url); ?>" class="button button-primary button-large">
                        Zu den Einstellungen
                    </a>
                </div>
            </div>
            <?php
            return;
        }

        ?>
        <div class="wrap umami-analytics-page">
            <div class="umami-header-bar">
                <h1 class="umami-page-title">
                    <span class="dashicons dashicons-chart-area"></span>
                    Analytics
                </h1>
                
                <div class="umami-range-selector">
                    <button class="umami-range-btn active" data-range="24h">24 Stunden</button>
                    <button class="umami-range-btn" data-range="7d">7 Tage</button>
                    <button class="umami-range-btn" data-range="30d">30 Tage</button>
                    <button class="umami-range-btn" data-range="6m">6 Monate</button>
                    <button class="umami-range-btn" data-range="1y">1 Jahr</button>
                </div>
            </div>
            
            <div id="umami-stats-container">
                <div class="umami-loading">
                    <span class="spinner is-active"></span>
                    <p>Lade Statistiken...</p>
                </div>
            </div>
        </div>

        <!-- CDN-Ressourcen -->
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js" crossorigin="anonymous"></script>
        <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3.0.0/dist/chartjs-adapter-date-fns.bundle.min.js" crossorigin="anonymous"></script>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" crossorigin="anonymous">

        <script>
        jQuery(document).ready(function($) {
            var currentRange = '24h';
            var charts = {};
            var currentAjax = null;
            // SICHERHEIT: Nonce für AJAX-Requests
            var umamiNonce = '<?php echo wp_create_nonce('umami_stats_nonce'); ?>';

            // Range Selector
            $('.umami-range-btn').on('click', function() {
                $('.umami-range-btn').removeClass('active');
                $(this).addClass('active');
                currentRange = $(this).data('range');
                loadStats();
            });

            function loadStats() {
                // Abort previous AJAX request if still running
                if (currentAjax) {
                    currentAjax.abort();
                }

                $('#umami-stats-container').html(
                    '<div class="umami-loading">' +
                    '<span class="spinner is-active"></span>' +
                    '<p>Lade Statistiken...</p>' +
                    '</div>'
                );

                // Destroy existing charts properly
                Object.keys(charts).forEach(function(key) {
                    if (charts[key] && typeof charts[key].destroy === 'function') {
                        try {
                            charts[key].destroy();
                        } catch(e) {
                            console.log('Chart destroy error:', e);
                        }
                    }
                });
                charts = {};

                currentAjax = $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'umami_get_stats',
                        range: currentRange,
                        nonce: umamiNonce  // SICHERHEIT: Nonce mitschicken
                    },
                    success: function(response) {
                        currentAjax = null;
                        if (response.success) {
                            displayStats(response.data);
                        } else {
                            $('#umami-stats-container').html(
                                '<div class="umami-error">' +
                                '<span class="dashicons dashicons-warning"></span>' +
                                '<p>' + (response.data || 'Fehler beim Laden der Statistiken') + '</p>' +
                                '</div>'
                            );
                        }
                    },
                    error: function(xhr) {
                        currentAjax = null;
                        if (xhr.statusText !== 'abort') {
                            $('#umami-stats-container').html(
                                '<div class="umami-error">' +
                                '<span class="dashicons dashicons-warning"></span>' +
                                '<p>Verbindungsfehler zur Umami API</p>' +
                                '</div>'
                            );
                        }
                    }
                });
            }

            function displayStats(data) {
                var html = '';
                
                // FIX: Metriken sind direkt Zahlen, nicht .value Objekte
                var visitors = data.visitors || 0;
                var pageviews = data.pageviews || 0;
                var visits = data.visits || 0;
                var bounces = data.bounces || 0;
                var totaltime = data.totaltime || 0;
                var bounceRate = visits > 0 ? (bounces / visits * 100) : 0;
                var avgDuration = visits > 0 ? Math.round(totaltime / visits) : 0; // Durchschnitt!

                // Metriken Header
                html += '<div class="umami-metrics-header">';
                html += '<div class="umami-metric-card">';
                html += '<div class="umami-metric-label">Besucher</div>';
                html += '<div class="umami-metric-value">' + visitors.toLocaleString() + '</div>';
                html += '</div>';
                
                html += '<div class="umami-metric-card">';
                html += '<div class="umami-metric-label">Aufrufe</div>';
                html += '<div class="umami-metric-value">' + pageviews.toLocaleString() + '</div>';
                html += '</div>';
                
                html += '<div class="umami-metric-card">';
                html += '<div class="umami-metric-label">Besuche</div>';
                html += '<div class="umami-metric-value">' + visits.toLocaleString() + '</div>';
                html += '</div>';
                
                html += '<div class="umami-metric-card">';
                html += '<div class="umami-metric-label">Absprungrate</div>';
                html += '<div class="umami-metric-value">' + bounceRate.toFixed(0) + '%</div>';
                html += '</div>';
                
                html += '<div class="umami-metric-card">';
                html += '<div class="umami-metric-label">Ø Besuchsdauer</div>';
                html += '<div class="umami-metric-value">' + formatDuration(avgDuration) + '</div>';
                html += '</div>';
                html += '</div>';

                // Timeline Line Chart - zwischen Metriken und Devices
                if (data.timeline && data.timeline.pageviews && data.timeline.sessions) {
                    html += '<div class="umami-timeline-chart">';
                    html += '<canvas id="umami-timeline-chart"></canvas>';
                    html += '</div>';
                }

                // DEVICE CARDS - Prominent!
                if (data.devices && Array.isArray(data.devices) && data.devices.length > 0) {
                    var totalDevices = data.devices.reduce((sum, d) => sum + d.y, 0);
                    
                    html += '<div class="umami-device-cards">';
                    data.devices.forEach(function(device) {
                        var percentage = totalDevices > 0 ? ((device.y / totalDevices) * 100).toFixed(1) : 0;
                        var icon = getDeviceIcon(device.x);
                        var deviceClass = device.x.toLowerCase().replace(' ', '-');
                        
                        html += '<div class="umami-device-card ' + deviceClass + '">';
                        html += '<div class="umami-device-icon">' + icon + '</div>';
                        html += '<div class="umami-device-info">';
                        html += '<div class="umami-device-label">' + escapeHtml(device.x) + '</div>';
                        html += '<div class="umami-device-stats">';
                        html += '<span class="umami-device-count">' + device.y.toLocaleString() + '</span>';
                        html += '<span class="umami-device-percentage">' + percentage + '%</span>';
                        html += '</div>';
                        html += '</div>';
                        html += '<div class="umami-device-bar">';
                        html += '<div class="umami-device-bar-fill" style="width: ' + percentage + '%"></div>';
                        html += '</div>';
                        html += '</div>';
                    });
                    html += '</div>';
                }

                // Two Column Layout
                html += '<div class="umami-two-column">';
                
                // Left Column
                html += '<div class="umami-column">';
                
                // Sources (Quellen)
                if (data.referrers && Array.isArray(data.referrers) && data.referrers.length > 0) {
                    html += renderSection('sources', 'Quellen', 'bi-link-45deg', data.referrers, true);
                }

                // Browsers
                if (data.browsers && Array.isArray(data.browsers) && data.browsers.length > 0) {
                    html += renderSection('browsers', 'Browser', 'bi-browser-chrome', data.browsers);
                }

                // Countries (Länder)
                if (data.countries && Array.isArray(data.countries) && data.countries.length > 0) {
                    html += renderSection('countries', 'Länder', 'bi-globe', data.countries, false, true);
                }

                html += '</div>'; // End left column
                
                // Right Column
                html += '<div class="umami-column">';
                
                // Pages (Seiten) - Einfache Liste ohne View-Switcher
                if (data.urls && Array.isArray(data.urls) && data.urls.length > 0) {
                    var totalPageviews = data.urls.slice(0, 10).reduce((sum, page) => sum + page.y, 0);
                    html += '<div class="umami-section" id="umami-section-pages">';
                    html += '<div class="umami-section-header">';
                    html += '<h3><i class="bi bi-file-earmark-text"></i> Seiten</h3>';
                    html += '</div>';
                    html += '<div class="umami-section-content">';
                    html += '<div class="umami-pages-list">';
                    data.urls.slice(0, 10).forEach(function(page) {
                        var percentage = totalPageviews > 0 ? ((page.y / totalPageviews) * 100).toFixed(0) : 0;
                        html += '<div class="umami-page-item">';
                        html += '<div class="umami-page-path">' + escapeHtml(page.x) + '</div>';
                        html += '<div class="umami-page-stats">';
                        html += '<span class="umami-page-count">' + page.y.toLocaleString() + '</span>';
                        html += '<span class="umami-page-percent">' + percentage + '%</span>';
                        html += '</div>';
                        html += '</div>';
                    });
                    html += '</div>';
                    html += '</div>';
                    html += '</div>';
                }

                // OS (Betriebssysteme)
                if (data.os && Array.isArray(data.os) && data.os.length > 0) {
                    html += renderSection('os', 'Betriebssysteme', 'bi-display', data.os);
                }

                html += '</div>'; // End right column
                html += '</div>'; // End two-column

                // Events Full Width
                if (data.events && Array.isArray(data.events) && data.events.length > 0) {
                    html += renderSection('events', 'Events', 'bi-lightning-charge', data.events);
                }

                $('#umami-stats-container').html(html);

                // Warte bis Chart.js geladen ist, dann erstelle Charts
                function waitForChart(callback) {
                    if (typeof Chart !== 'undefined') {
                        callback();
                    } else {
                        console.log('Waiting for Chart.js to load...');
                        setTimeout(function() { waitForChart(callback); }, 100);
                    }
                }

                waitForChart(function() {
                    // Create timeline chart first
                    if (data.timeline && data.timeline.pageviews && data.timeline.sessions) {
                        setTimeout(function() {
                            createTimelineChart(data.timeline);
                        }, 100);
                    }

                    // Create initial charts
                    setTimeout(function() {
                        createAllCharts(data);
                    }, 200);
                });

                // Attach view switcher event handlers
                $('.umami-view-switcher i').on('click', function() {
                    var $switcher = $(this).parent();
                    var section = $switcher.data('section');
                    var view = $(this).data('view');
                    
                    // Save to localStorage
                    localStorage.setItem('umami_view_' + section, view);
                    
                    // Update active state
                    $switcher.find('i').removeClass('active');
                    $(this).addClass('active');
                    
                    // Re-render section
                    var sectionData = getSectionData(section, data);
                    if (sectionData) {
                        var isReferrer = section === 'sources';
                        var isCountry = section === 'countries';
                        renderSectionContent(section, view, sectionData, isReferrer, isCountry);
                    }
                });
            }

            function renderSection(id, title, icon, sectionData, isReferrer, isCountry) {
                var savedView = localStorage.getItem('umami_view_' + id) || 'list';
                
                var html = '<div class="umami-section" id="umami-section-' + id + '">';
                html += '<div class="umami-section-header">';
                html += '<h3><i class="bi ' + icon + '"></i> ' + title + '</h3>';
                html += '<div class="umami-view-switcher" data-section="' + id + '">';
                html += '<i class="bi bi-list-ul ' + (savedView === 'list' ? 'active' : '') + '" data-view="list" title="Liste"></i>';
                html += '<i class="bi bi-bar-chart-fill ' + (savedView === 'bar' ? 'active' : '') + '" data-view="bar" title="Bar Chart"></i>';
                html += '<i class="bi bi-pie-chart-fill ' + (savedView === 'donut' ? 'active' : '') + '" data-view="donut" title="Donut Chart"></i>';
                html += '</div>';
                html += '</div>';
                html += '<div class="umami-section-content" id="umami-content-' + id + '">';
                html += renderSectionContentHTML(id, savedView, sectionData, isReferrer, isCountry);
                html += '</div>';
                html += '</div>';
                
                return html;
            }

            function renderSectionContentHTML(id, view, sectionData, isReferrer, isCountry) {
                if (view === 'list') {
                    return renderListView(id, sectionData, isReferrer, isCountry);
                } else if (view === 'bar') {
                    return '<div class="umami-chart-container" style="height: 300px;"><canvas id="umami-chart-' + id + '"></canvas></div>';
                } else if (view === 'donut') {
                    return '<div class="umami-chart-container" style="height: 300px;"><canvas id="umami-chart-' + id + '"></canvas></div>';
                }
            }

            function renderSectionContent(id, view, sectionData, isReferrer, isCountry) {
                var $content = $('#umami-content-' + id);
                var html = renderSectionContentHTML(id, view, sectionData, isReferrer, isCountry);
                $content.html(html);
                
                // Create chart if needed
                if (view === 'bar' || view === 'donut') {
                    setTimeout(function() {
                        createSingleChart(id, view, sectionData);
                    }, 100);
                }
            }

            // SICHERHEIT: Domain-Validierung für Favicons
            function isValidDomain(domain) {
                if (!domain || domain === '(Direkt)') {
                    return false;
                }
                // Einfache Domain-Validierung
                return /^[a-zA-Z0-9][a-zA-Z0-9-]{0,61}[a-zA-Z0-9]?\.[a-zA-Z]{2,}$/.test(domain);
            }

            function renderListView(sectionId, items, isReferrer, isCountry) {
                var html = '<div class="umami-list">';
                var maxValue = Math.max(...items.slice(0, 10).map(i => i.y));
                
                items.slice(0, 10).forEach(function(item) {
                    var percentage = maxValue > 0 ? (item.y / maxValue) * 100 : 0;
                    var domain = item.x || '(Direkt)';
                    
                    html += '<div class="umami-list-item">';
                    html += '<div class="umami-list-header">';
                    html += '<span class="umami-list-label">';
                    
                    // Referrer with favicon - SICHERHEIT: Domain validieren
                    if (isReferrer || sectionId === 'sources') {
                        if (isValidDomain(domain)) {
                            var faviconUrl = 'https://www.google.com/s2/favicons?domain=' + encodeURIComponent(domain) + '&sz=16';
                            var referrerUrl = 'https://' + encodeURIComponent(domain);
                            
                            html += '<img src="' + faviconUrl + '" class="umami-favicon" onerror="this.style.display=\'none\'">';
                            html += '<a href="' + referrerUrl + '" target="_blank" rel="noopener noreferrer" class="umami-referrer-link">';
                            html += escapeHtml(domain);
                            html += '</a>';
                        } else {
                            html += escapeHtml(domain);
                        }
                    }
                    // Browser with icon
                    else if (sectionId === 'browsers') {
                        html += '<i class="bi ' + getBrowserIcon(domain) + ' umami-list-icon"></i>';
                        html += escapeHtml(domain);
                    }
                    // OS with icon
                    else if (sectionId === 'os') {
                        html += '<i class="bi ' + getOSIconClass(domain) + ' umami-list-icon"></i>';
                        html += escapeHtml(domain);
                    }
                    // Country with flag
                    else if (isCountry || sectionId === 'countries') {
                        html += '<span class="umami-flag">' + getCountryFlag(domain) + '</span>';
                        html += escapeHtml(getCountryName(domain));
                    }
                    // Default (pages, events)
                    else {
                        html += escapeHtml(domain);
                    }
                    
                    html += '</span>';
                    html += '<span class="umami-list-value">' + item.y.toLocaleString() + '</span>';
                    html += '</div>';
                    html += '<div class="umami-progress-bar">';
                    html += '<div class="umami-progress-fill" style="width: ' + percentage + '%"></div>';
                    html += '</div>';
                    html += '</div>';
                });
                
                html += '</div>';
                return html;
            }

            function getSectionData(section, data) {
                var map = {
                    'sources': 'referrers',
                    'browsers': 'browsers',
                    'os': 'os',
                    'countries': 'countries',
                    'events': 'events'
                };
                return data[map[section]];
            }

            function createAllCharts(data) {
                ['sources', 'browsers', 'os', 'countries', 'events'].forEach(function(section) {
                    var view = localStorage.getItem('umami_view_' + section) || 'list';
                    if (view === 'bar' || view === 'donut') {
                        var sectionData = getSectionData(section, data);
                        if (sectionData && Array.isArray(sectionData) && sectionData.length > 0) {
                            createSingleChart(section, view, sectionData);
                        }
                    }
                });
            }

            function createSingleChart(id, view, data) {
                var canvas = document.getElementById('umami-chart-' + id);
                if (!canvas) return;

                // Prüfe ob Chart.js verfügbar ist
                if (typeof Chart === 'undefined') {
                    console.error('Chart.js is not loaded!');
                    return;
                }

                // Destroy existing chart
                if (charts[id]) {
                    try {
                        charts[id].destroy();
                    } catch(e) {}
                }

                var gradient1 = '<?php echo esc_js(get_option('umami_gradient_start', '#667eea')); ?>';
                var gradient2 = '<?php echo esc_js(get_option('umami_gradient_end', '#764ba2')); ?>';
                var chartData = data.slice(0, view === 'donut' ? 6 : 8);

                // For bar charts: add icons/flags. For donut: just text
                var labels = chartData.map(function(d) {
                    if (view === 'donut') {
                        // Donut: nur Text, keine Icons/Flags
                        return d.x;
                    } else {
                        // Bar Chart: mit Icons/Flags
                        if (id === 'browsers') {
                            return d.x; // Will add icon via plugin
                        } else if (id === 'os') {
                            return d.x; // Will add icon via plugin
                        } else if (id === 'countries') {
                            return getCountryFlag(d.x) + ' ' + getCountryName(d.x);
                        } else if (id === 'sources') {
                            return d.x; // Favicons will be added via plugin
                        }
                        return d.x;
                    }
                });

                // Store original data for favicon plugin
                if (id === 'sources' && view === 'bar') {
                    canvas.dataset.sourceData = JSON.stringify(chartData.map(d => d.x));
                }
                if ((id === 'browsers' || id === 'os') && view === 'bar') {
                    canvas.dataset.itemData = JSON.stringify(chartData.map(d => d.x));
                    canvas.dataset.itemType = id;
                }

                if (view === 'bar') {
                    var plugins = [];
                    
                    // Custom plugin for favicons/icons
                    if (id === 'sources' || id === 'browsers' || id === 'os') {
                        plugins.push({
                            id: 'customIcons',
                            afterDraw: function(chart) {
                                var ctx = chart.ctx;
                                var yAxis = chart.scales.y;
                                
                                if (id === 'sources') {
                                    var sources = JSON.parse(chart.canvas.dataset.sourceData || '[]');
                                    yAxis.ticks.forEach(function(tick, index) {
                                        var domain = sources[index];
                                        // SICHERHEIT: Domain validieren
                                        if (isValidDomain(domain)) {
                                            var y = yAxis.getPixelForTick(index);
                                            var img = new Image();
                                            img.src = 'https://www.google.com/s2/favicons?domain=' + encodeURIComponent(domain) + '&sz=16';
                                            img.onload = function() {
                                                ctx.drawImage(img, yAxis.left - 24, y - 8, 16, 16);
                                            };
                                        }
                                    });
                                } else if (id === 'browsers' || id === 'os') {
                                    var items = JSON.parse(chart.canvas.dataset.itemData || '[]');
                                    yAxis.ticks.forEach(function(tick, index) {
                                        var itemName = items[index];
                                        if (itemName) {
                                            var iconClass = id === 'browsers' ? getBrowserIcon(itemName) : getOSIconClass(itemName);
                                            // Note: Can't easily render Bootstrap icons in canvas
                                            // Would need to convert icon to image or use font rendering
                                        }
                                    });
                                }
                            }
                        });
                    }
                    
                    charts[id] = new Chart(canvas, {
                        type: 'bar',
                        data: {
                            labels: labels,
                            datasets: [{
                                data: chartData.map(d => d.y),
                                backgroundColor: gradient1,
                                borderRadius: 6,
                                barThickness: 25
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            return context.parsed.y.toLocaleString();
                                        }
                                    }
                                }
                            },
                            scales: {
                                x: {
                                    grid: { display: false },
                                    ticks: {
                                        font: { size: 11 }
                                    }
                                },
                                y: {
                                    beginAtZero: true,
                                    grid: { color: '#f0f0f1' },
                                    ticks: {
                                        font: { size: 11 },
                                        callback: function(value, index) {
                                            var label = this.getLabelForValue(value);
                                            // For sources, add space for favicon
                                            if (id === 'sources') {
                                                return '     ' + label;
                                            }
                                            return label;
                                        }
                                    }
                                }
                            }
                        },
                        plugins: plugins
                    });
                } else if (view === 'donut') {
                    var colors = generateGradientSteps(chartData.length, gradient1, gradient2);
                    
                    charts[id] = new Chart(canvas, {
                        type: 'doughnut',
                        data: {
                            labels: labels,
                            datasets: [{
                                data: chartData.map(d => d.y),
                                backgroundColor: colors,
                                borderWidth: 3,
                                borderColor: '#fff'
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: {
                                        padding: 15,
                                        usePointStyle: true,
                                        font: { size: 11 },
                                        generateLabels: function(chart) {
                                            var data = chart.data;
                                            if (data.labels.length && data.datasets.length) {
                                                return data.labels.map(function(label, i) {
                                                    var value = data.datasets[0].data[i];
                                                    var total = data.datasets[0].data.reduce((a, b) => a + b, 0);
                                                    var percent = ((value / total) * 100).toFixed(1);
                                                    return {
                                                        text: label + ' (' + percent + '%)',
                                                        fillStyle: data.datasets[0].backgroundColor[i],
                                                        hidden: false,
                                                        index: i
                                                    };
                                                });
                                            }
                                            return [];
                                        }
                                    }
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            var total = context.dataset.data.reduce((a, b) => a + b, 0);
                                            var percentage = ((context.parsed / total) * 100).toFixed(1);
                                            return context.label + ': ' + context.parsed.toLocaleString() + ' (' + percentage + '%)';
                                        }
                                    }
                                }
                            }
                        }
                    });
                }
            }

            function createTimelineChart(timeline) {
                var canvas = document.getElementById('umami-timeline-chart');
                if (!canvas) {
                    console.warn('Timeline chart canvas not found');
                    return;
                }

                // Prüfe ob Chart.js verfügbar ist
                if (typeof Chart === 'undefined') {
                    console.error('Chart.js is not loaded!');
                    return;
                }

                console.log('Creating timeline chart...');

                // Destroy existing chart
                if (charts.timeline) {
                    try {
                        charts.timeline.destroy();
                    } catch(e) {}
                }

                var gradient1 = '<?php echo esc_js(get_option('umami_gradient_start', '#667eea')); ?>';
                
                // Generate two harmonious colors based on gradient start
                var hsl = rgbToHsl(gradient1);
                var color1 = hslToRgb(hsl.h, hsl.s, hsl.l); // Original
                var color2 = hslToRgb((hsl.h + 40) % 360, Math.max(60, hsl.s - 10), Math.min(65, hsl.l + 10)); // Shifted hue, slightly lighter

                // Prepare data
                var labels = timeline.pageviews.map(function(item) {
                    return new Date(item.x);
                });
                
                var pageviewsData = timeline.pageviews.map(function(item) {
                    return item.y;
                });
                
                var sessionsData = timeline.sessions.map(function(item) {
                    return item.y;
                });

                charts.timeline = new Chart(canvas, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [
                            {
                                label: 'Besucher',
                                data: sessionsData,
                                borderColor: color1,
                                backgroundColor: hexToRgba(color1, 0.1),
                                borderWidth: 3,
                                fill: true,
                                tension: 0.4,
                                pointRadius: 3,
                                pointHoverRadius: 5,
                                pointBackgroundColor: color1,
                                pointBorderColor: '#fff',
                                pointBorderWidth: 2
                            },
                            {
                                label: 'Aufrufe',
                                data: pageviewsData,
                                borderColor: color2,
                                backgroundColor: hexToRgba(color2, 0.1),
                                borderWidth: 3,
                                fill: true,
                                tension: 0.4,
                                pointRadius: 3,
                                pointHoverRadius: 5,
                                pointBackgroundColor: color2,
                                pointBorderColor: '#fff',
                                pointBorderWidth: 2
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            mode: 'index',
                            intersect: false
                        },
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top',
                                align: 'end',
                                labels: {
                                    usePointStyle: true,
                                    padding: 15,
                                    font: { size: 12, weight: '500' }
                                }
                            },
                            tooltip: {
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                padding: 12,
                                titleFont: { size: 13, weight: 'bold' },
                                bodyFont: { size: 12 },
                                bodySpacing: 6,
                                callbacks: {
                                    title: function(context) {
                                        var date = context[0].parsed.x;
                                        return new Date(date).toLocaleDateString('de-CH', {
                                            day: '2-digit',
                                            month: 'short',
                                            year: 'numeric',
                                            hour: context[0].dataset.data.length > 48 ? undefined : '2-digit'
                                        });
                                    },
                                    label: function(context) {
                                        return context.dataset.label + ': ' + context.parsed.y.toLocaleString();
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                type: 'time',
                                time: {
                                    displayFormats: {
                                        hour: 'HH:mm',
                                        day: 'dd MMM',
                                        month: 'MMM yyyy'
                                    }
                                },
                                grid: {
                                    display: false
                                },
                                ticks: {
                                    maxRotation: 0,
                                    autoSkipPadding: 20,
                                    font: { size: 11 }
                                }
                            },
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: '#f0f0f1'
                                },
                                ticks: {
                                    font: { size: 11 }
                                }
                            }
                        }
                    }
                });
                
                console.log('✓ Timeline chart created successfully');
            }

            function hexToRgba(color, alpha) {
                // Convert RGB string or hex to rgba
                if (color.startsWith('rgb')) {
                    return color.replace('rgb', 'rgba').replace(')', ', ' + alpha + ')');
                }
                // For hex colors
                var hex = color.replace('#', '');
                var r = parseInt(hex.substr(0, 2), 16);
                var g = parseInt(hex.substr(2, 2), 16);
                var b = parseInt(hex.substr(4, 2), 16);
                return 'rgba(' + r + ', ' + g + ', ' + b + ', ' + alpha + ')';
            }

            function generateGradientSteps(count, color1, color2) {
                // Verwende Farbharmonien basierend auf der Startfarbe
                return generateColorHarmony(count, color1);
            }

            function generateColorHarmony(count, baseColor) {
                var hsl = rgbToHsl(baseColor);
                var colors = [];
                
                if (count <= 1) {
                    return [baseColor];
                }
                
                // Wähle Harmonie basierend auf Anzahl
                if (count === 2) {
                    // Komplementär (180°)
                    colors = [
                        hslToRgb(hsl.h, hsl.s, hsl.l),
                        hslToRgb((hsl.h + 180) % 360, hsl.s, hsl.l)
                    ];
                } else if (count === 3) {
                    // Triadisch (120°)
                    colors = [
                        hslToRgb(hsl.h, hsl.s, hsl.l),
                        hslToRgb((hsl.h + 120) % 360, hsl.s, hsl.l),
                        hslToRgb((hsl.h + 240) % 360, hsl.s, hsl.l)
                    ];
                } else if (count === 4) {
                    // Tetradisch (90°)
                    colors = [
                        hslToRgb(hsl.h, hsl.s, hsl.l),
                        hslToRgb((hsl.h + 90) % 360, hsl.s, hsl.l),
                        hslToRgb((hsl.h + 180) % 360, hsl.s, hsl.l),
                        hslToRgb((hsl.h + 270) % 360, hsl.s, hsl.l)
                    ];
                } else if (count <= 6) {
                    // Split-Komplementär erweitert
                    var angle = 360 / count;
                    for (var i = 0; i < count; i++) {
                        colors.push(hslToRgb((hsl.h + (angle * i)) % 360, hsl.s, hsl.l));
                    }
                } else {
                    // Für mehr Items: Tetradisch + Helligkeitsvariationen
                    var baseAngles = [0, 90, 180, 270];
                    var lightnessVariations = [0, -15, 15]; // Dunkel, Normal, Hell
                    
                    for (var i = 0; i < count; i++) {
                        var angleIndex = i % baseAngles.length;
                        var lightIndex = Math.floor(i / baseAngles.length) % lightnessVariations.length;
                        var newHue = (hsl.h + baseAngles[angleIndex]) % 360;
                        var newLight = Math.max(30, Math.min(70, hsl.l + lightnessVariations[lightIndex]));
                        colors.push(hslToRgb(newHue, hsl.s, newLight));
                    }
                }
                
                return colors.slice(0, count);
            }

            function rgbToHsl(hex) {
                // Entferne # wenn vorhanden
                hex = hex.replace('#', '');
                
                var r = parseInt(hex.substr(0, 2), 16) / 255;
                var g = parseInt(hex.substr(2, 2), 16) / 255;
                var b = parseInt(hex.substr(4, 2), 16) / 255;
                
                var max = Math.max(r, g, b);
                var min = Math.min(r, g, b);
                var h, s, l = (max + min) / 2;
                
                if (max === min) {
                    h = s = 0;
                } else {
                    var d = max - min;
                    s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
                    
                    switch (max) {
                        case r: h = ((g - b) / d + (g < b ? 6 : 0)) / 6; break;
                        case g: h = ((b - r) / d + 2) / 6; break;
                        case b: h = ((r - g) / d + 4) / 6; break;
                    }
                }
                
                return {
                    h: Math.round(h * 360),
                    s: Math.round(s * 100),
                    l: Math.round(l * 100)
                };
            }

            function hslToRgb(h, s, l) {
                h = h / 360;
                s = s / 100;
                l = l / 100;
                
                var r, g, b;
                
                if (s === 0) {
                    r = g = b = l;
                } else {
                    var hue2rgb = function(p, q, t) {
                        if (t < 0) t += 1;
                        if (t > 1) t -= 1;
                        if (t < 1/6) return p + (q - p) * 6 * t;
                        if (t < 1/2) return q;
                        if (t < 2/3) return p + (q - p) * (2/3 - t) * 6;
                        return p;
                    };
                    
                    var q = l < 0.5 ? l * (1 + s) : l + s - l * s;
                    var p = 2 * l - q;
                    
                    r = hue2rgb(p, q, h + 1/3);
                    g = hue2rgb(p, q, h);
                    b = hue2rgb(p, q, h - 1/3);
                }
                
                return 'rgb(' + Math.round(r * 255) + ', ' + Math.round(g * 255) + ', ' + Math.round(b * 255) + ')';
            }

            function getDeviceIcon(device) {
                var icons = {
                    'desktop': '<i class="bi bi-pc-display"></i>',
                    'mobile': '<i class="bi bi-phone"></i>',
                    'tablet': '<i class="bi bi-tablet"></i>',
                    'laptop': '<i class="bi bi-laptop"></i>'
                };
                return icons[device.toLowerCase()] || '<i class="bi bi-question-circle"></i>';
            }

            function getOSIcon(os) {
                if (os.includes('Windows')) return 'bi bi-windows';
                if (os.includes('Mac') || os.includes('macOS')) return 'bi bi-apple';
                if (os.includes('Linux')) return 'bi bi-ubuntu';
                if (os.includes('Android')) return 'bi bi-android2';
                if (os.includes('iOS')) return 'bi bi-apple';
                return 'bi bi-display';
            }

            function getBrowserIcon(name) {
                var icons = {
                    'chrome': 'bi-browser-chrome',
                    'firefox': 'bi-browser-firefox',
                    'safari': 'bi-browser-safari',
                    'edge': 'bi-browser-edge',
                    'edge-chromium': 'bi-browser-edge',
                    'opera': 'bi-browser-chrome',
                    'brave': 'bi-browser-chrome',
                    'ios': 'bi-phone',
                    'samsung': 'bi-phone'
                };
                return icons[name.toLowerCase()] || 'bi-browser-chrome';
            }

            function getBrowserLabel(name) {
                return name; // Nur Name ohne Icon (für Charts)
            }

            function getOSIconClass(name) {
                if (name.includes('Windows')) return 'bi-windows';
                if (name.includes('Mac') || name.includes('macOS')) return 'bi-apple';
                if (name.includes('Linux')) return 'bi-ubuntu';
                if (name.includes('Android')) return 'bi-android2';
                if (name.includes('iOS')) return 'bi-apple';
                return 'bi-laptop';
            }

            function getOSLabel(name) {
                return name; // Nur Name ohne Icon (für Charts)
            }

            function getCountryLabel(code) {
                return getCountryName(code); // Nur Name ohne Flag (für Charts)
            }

            function getCountryFlag(code) {
                var flags = {
                    'CH': '🇨🇭', 'DE': '🇩🇪', 'AT': '🇦🇹', 'FR': '🇫🇷', 'IT': '🇮🇹',
                    'US': '🇺🇸', 'GB': '🇬🇧', 'NL': '🇳🇱', 'BE': '🇧🇪', 'ES': '🇪🇸',
                    'PT': '🇵🇹', 'SE': '🇸🇪', 'NO': '🇳🇴', 'DK': '🇩🇰', 'FI': '🇫🇮',
                    'PL': '🇵🇱', 'CZ': '🇨🇿', 'RO': '🇷🇴', 'GR': '🇬🇷', 'HU': '🇭🇺',
                    'IE': '🇮🇪', 'SK': '🇸🇰', 'BG': '🇧🇬', 'HR': '🇭🇷', 'SI': '🇸🇮'
                };
                return flags[code] || '🌍';
            }

            function getCountryName(code) {
                var countries = {
                    'CH': 'Schweiz', 'DE': 'Deutschland', 'AT': 'Österreich',
                    'FR': 'Frankreich', 'IT': 'Italien', 'US': 'USA',
                    'GB': 'Grossbritannien', 'NL': 'Niederlande', 'BE': 'Belgien',
                    'ES': 'Spanien', 'PT': 'Portugal', 'SE': 'Schweden',
                    'NO': 'Norwegen', 'DK': 'Dänemark', 'FI': 'Finnland',
                    'PL': 'Polen', 'CZ': 'Tschechien', 'RO': 'Rumänien',
                    'GR': 'Griechenland', 'HU': 'Ungarn', 'IE': 'Irland',
                    'SK': 'Slowakei', 'BG': 'Bulgarien', 'HR': 'Kroatien', 'SI': 'Slowenien'
                };
                return countries[code] || code;
            }

            function formatDuration(seconds) {
                if (seconds < 60) return seconds + 's';
                var minutes = Math.floor(seconds / 60);
                var secs = seconds % 60;
                return minutes + 'm ' + secs + 's';
            }

            function escapeHtml(text) {
                var map = {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                };
                return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
            }

            loadStats();
        });
        </script>
        <?php
    }

    /**
     * AJAX Handler für Stats - MIT NONCE-PRÜFUNG
     */
    public function ajax_get_stats() {
        // SICHERHEIT: Nonce prüfen (CSRF-Schutz)
        check_ajax_referer('umami_stats_nonce', 'nonce');
        
        if (!$this->user_can_view_stats()) {
            wp_send_json_error('Keine Berechtigung');
            return;
        }

        $range = isset($_POST['range']) ? sanitize_text_field($_POST['range']) : '24h';
        $stats = $this->get_umami_stats($range);
        
        if ($stats) {
            wp_send_json_success($stats);
        } else {
            wp_send_json_error('Fehler beim Abrufen der Statistiken');
        }
    }

    /**
     * Holt Umami Stats via API
     */
    private function get_umami_stats($range = '24h') {
        $website_id = get_option('umami_website_id', '');
        
        if (empty($website_id)) {
            return false;
        }

        $token = $this->get_api_token();
        if (!$token) {
            return false;
        }

        // Zeitraum berechnen
        $end_at = time() * 1000;
        switch ($range) {
            case '7d':
                $start_at = $end_at - (7 * 24 * 60 * 60 * 1000);
                break;
            case '30d':
                $start_at = $end_at - (30 * 24 * 60 * 60 * 1000);
                break;
            case '6m':
                $start_at = $end_at - (180 * 24 * 60 * 60 * 1000);
                break;
            case '1y':
                $start_at = $end_at - (365 * 24 * 60 * 60 * 1000);
                break;
            case '24h':
            default:
                $start_at = $end_at - (24 * 60 * 60 * 1000);
                break;
        }

        $headers = array(
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json'
        );

        $data = array();

        // 1. Stats (pageviews, visits, bounces, totaltime)
        $stats_url = $this->api_url . '/websites/' . $website_id . '/stats?startAt=' . $start_at . '&endAt=' . $end_at;
        $response = wp_remote_get($stats_url, array('headers' => $headers, 'timeout' => 15));
        if (!is_wp_error($response)) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            if ($body) {
                $data = array_merge($data, $body);
            }
        }

        // 1b. Pageviews Timeline (for line chart)
        // Determine unit based on range
        $range_seconds = ($end_at - $start_at) / 1000;
        if ($range_seconds <= 48 * 3600) { // <= 48 hours
            $unit = 'hour';
        } elseif ($range_seconds <= 90 * 86400) { // <= 90 days
            $unit = 'day';
        } else {
            $unit = 'month';
        }
        
        $pageviews_url = $this->api_url . '/websites/' . $website_id . '/pageviews?startAt=' . $start_at . '&endAt=' . $end_at . '&unit=' . $unit;
        $response = wp_remote_get($pageviews_url, array('headers' => $headers, 'timeout' => 15));
        if (!is_wp_error($response)) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            if ($body && is_array($body) && !isset($body['error'])) {
                $data['timeline'] = $body;
            }
        }

        // 2. URLs (Top Pages) - Use type=path according to Umami API docs
        $urls_url = $this->api_url . '/websites/' . $website_id . '/metrics?startAt=' . $start_at . '&endAt=' . $end_at . '&type=path';
        $response = wp_remote_get($urls_url, array('headers' => $headers, 'timeout' => 15));
        if (!is_wp_error($response)) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            // Only set if it's an array (not an error object)
            if ($body && is_array($body) && !isset($body['error'])) {
                $data['urls'] = $body;
            }
        }

        // 3. Referrers (Sources)
        $referrer_url = $this->api_url . '/websites/' . $website_id . '/metrics?startAt=' . $start_at . '&endAt=' . $end_at . '&type=referrer';
        $response = wp_remote_get($referrer_url, array('headers' => $headers, 'timeout' => 15));
        if (!is_wp_error($response)) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            if ($body) {
                $data['referrers'] = $body;
            }
        }

        // 4. Browsers
        $browser_url = $this->api_url . '/websites/' . $website_id . '/metrics?startAt=' . $start_at . '&endAt=' . $end_at . '&type=browser';
        $response = wp_remote_get($browser_url, array('headers' => $headers, 'timeout' => 15));
        if (!is_wp_error($response)) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            if ($body) {
                $data['browsers'] = $body;
            }
        }

        // 5. OS
        $os_url = $this->api_url . '/websites/' . $website_id . '/metrics?startAt=' . $start_at . '&endAt=' . $end_at . '&type=os';
        $response = wp_remote_get($os_url, array('headers' => $headers, 'timeout' => 15));
        if (!is_wp_error($response)) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            if ($body) {
                $data['os'] = $body;
            }
        }

        // 6. Devices
        $device_url = $this->api_url . '/websites/' . $website_id . '/metrics?startAt=' . $start_at . '&endAt=' . $end_at . '&type=device';
        $response = wp_remote_get($device_url, array('headers' => $headers, 'timeout' => 15));
        if (!is_wp_error($response)) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            if ($body) {
                $data['devices'] = $body;
            }
        }

        // 7. Countries
        $country_url = $this->api_url . '/websites/' . $website_id . '/metrics?startAt=' . $start_at . '&endAt=' . $end_at . '&type=country';
        $response = wp_remote_get($country_url, array('headers' => $headers, 'timeout' => 15));
        if (!is_wp_error($response)) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            if ($body) {
                $data['countries'] = $body;
            }
        }

        // 8. Events
        $events_url = $this->api_url . '/websites/' . $website_id . '/metrics?startAt=' . $start_at . '&endAt=' . $end_at . '&type=event';
        $response = wp_remote_get($events_url, array('headers' => $headers, 'timeout' => 15));
        if (!is_wp_error($response)) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            if ($body) {
                $data['events'] = $body;
            }
        }

        return $data;
    }

    /**
     * Holt API Token via Login
     */
    private function get_api_token() {
        // Check cached token
        $cached_token = get_transient('umami_api_token');
        if ($cached_token) {
            return $cached_token;
        }

        $username = get_option('umami_username', '');
        $password_encrypted = get_option('umami_password', '');

        if (empty($username) || empty($password_encrypted)) {
            return false;
        }

        // Entschlüssele Passwort
        $password = $password_encrypted;
        if (strpos($password_encrypted, 'enc:') === 0) {
            $password = $this->decrypt_password(substr($password_encrypted, 4));
        }

        $login_url = $this->api_url . '/auth/login';
        
        $response = wp_remote_post($login_url, array(
            'headers' => array(
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'username' => $username,
                'password' => $password
            )),
            'timeout' => 15
        ));

        if (is_wp_error($response)) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['token'])) {
            // Cache token für 23 Stunden (läuft nach 24h ab)
            set_transient('umami_api_token', $data['token'], 23 * HOUR_IN_SECONDS);
            return $data['token'];
        }

        return false;
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
            array($this, 'display_settings_page')
        );
    }

    /**
     * Prüft ob User die Stats sehen darf
     */
    private function user_can_view_stats() {
        $user = wp_get_current_user();
        $allowed_roles = get_option('umami_allowed_roles', array('administrator', 'editor'));

        foreach ($allowed_roles as $role) {
            if (in_array($role, $user->roles)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Fügt Dashboard Widget hinzu
     */
    public function add_dashboard_widget() {
        if (!$this->user_can_view_stats()) {
            return;
        }

        wp_add_dashboard_widget(
            'umami_stats_widget',
            'Website Statistiken',
            array($this, 'display_dashboard_widget')
        );
    }

    /**
     * Zeigt das Dashboard Widget
     */
    public function display_dashboard_widget() {
        $username = get_option('umami_username', '');
        $password = get_option('umami_password', '');
        $website_id = get_option('umami_website_id', '');
        $settings_url = admin_url('options-general.php?page=umami-stats-settings');
        $analytics_url = admin_url('admin.php?page=umami-analytics');

        ?>
        <div class="umami-widget-content">
            <?php if (empty($username) || empty($password) || empty($website_id)): ?>
                <div class="umami-widget-setup">
                    <span class="dashicons dashicons-chart-area umami-icon-large"></span>
                    <p class="umami-widget-text">Noch nicht konfiguriert</p>
                    <a href="<?php echo esc_url($settings_url); ?>" class="button button-primary">
                        Einstellungen öffnen
                    </a>
                </div>
            <?php else: ?>
                <div class="umami-widget-main">
                    <div class="umami-widget-icon">
                        <span class="dashicons dashicons-chart-area"></span>
                    </div>
                    <div class="umami-widget-info">
                        <p class="umami-widget-description">
                            Schau dir deine Website-Statistiken und Besucheranalysen an
                        </p>
                    </div>
                    <div class="umami-widget-actions">
                        <a href="<?php echo esc_url($analytics_url); ?>" 
                           class="umami-stats-button">
                            <span class="dashicons dashicons-chart-area"></span>
                            Statistiken öffnen
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Zeigt die Einstellungsseite
     */
    public function display_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Du hast keine Berechtigung, diese Seite zu sehen.'));
        }

        // Test Connection Button Handler
        if (isset($_POST['umami_test_connection']) && check_admin_referer('umami_test_connection')) {
            delete_transient('umami_api_token'); // Clear cached token
            $token = $this->get_api_token();
            if ($token) {
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
                settings_fields('umami_stats_settings');
                do_settings_sections('umami-stats-settings');
                submit_button();
                ?>
            </form>

            <hr>

            <h2>Verbindung testen</h2>
            <form method="post">
                <?php wp_nonce_field('umami_test_connection'); ?>
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
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Umami API Credentials
        register_setting('umami_stats_settings', 'umami_username', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
        ));

        register_setting('umami_stats_settings', 'umami_password', array(
            'type' => 'string',
            'sanitize_callback' => array($this, 'sanitize_password'),
            'default' => ''
        ));

        register_setting('umami_stats_settings', 'umami_website_id', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
        ));

        // Erlaubte Rollen
        register_setting('umami_stats_settings', 'umami_allowed_roles', array(
            'type' => 'array',
            'sanitize_callback' => array($this, 'sanitize_roles'),
            'default' => array('administrator', 'editor')
        ));

        // Gradient Farben
        register_setting('umami_stats_settings', 'umami_gradient_start', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_hex_color',
            'default' => '#667eea'
        ));

        register_setting('umami_stats_settings', 'umami_gradient_end', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_hex_color',
            'default' => '#764ba2'
        ));

        register_setting('umami_stats_settings', 'umami_button_text_color', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_hex_color',
            'default' => '#ffffff'
        ));

        // API Settings Section
        add_settings_section(
            'umami_stats_api_section',
            'API Zugangsdaten',
            array($this, 'api_section_callback'),
            'umami-stats-settings'
        );

        // Username
        add_settings_field(
            'umami_username',
            'Benutzername',
            array($this, 'username_field_callback'),
            'umami-stats-settings',
            'umami_stats_api_section'
        );

        // Password
        add_settings_field(
            'umami_password',
            'Passwort',
            array($this, 'password_field_callback'),
            'umami-stats-settings',
            'umami_stats_api_section'
        );

        // Website ID
        add_settings_field(
            'umami_website_id',
            'Website-ID',
            array($this, 'website_id_field_callback'),
            'umami-stats-settings',
            'umami_stats_api_section'
        );

        // Design Settings Section
        add_settings_section(
            'umami_stats_design_section',
            'Design & Berechtigungen',
            array($this, 'design_section_callback'),
            'umami-stats-settings'
        );

        // Rollen Feld
        add_settings_field(
            'umami_allowed_roles',
            'Berechtigte Rollen',
            array($this, 'allowed_roles_field_callback'),
            'umami-stats-settings',
            'umami_stats_design_section'
        );

        // Gradient Farben Feld
        add_settings_field(
            'umami_gradient_colors',
            'Gradient Farben',
            array($this, 'gradient_colors_field_callback'),
            'umami-stats-settings',
            'umami_stats_design_section'
        );
    }

    /**
     * Sanitize Rollen Array
     */
    public function sanitize_roles($input) {
        if (!is_array($input)) {
            return array('administrator');
        }

        $wp_roles = wp_roles();
        $valid_roles = array();

        foreach ($input as $role) {
            if (isset($wp_roles->roles[$role])) {
                $valid_roles[] = $role;
            }
        }

        if (empty($valid_roles)) {
            $valid_roles[] = 'administrator';
        }

        return $valid_roles;
    }

    /**
     * Sanitize und verschlüssele Passwort
     */
    public function sanitize_password($input) {
        if (empty($input)) {
            return '';
        }
        // Nur verschlüsseln wenn es ein neues/geändertes Passwort ist
        // Wenn es mit "enc:" beginnt, ist es bereits verschlüsselt
        if (strpos($input, 'enc:') === 0) {
            return $input;
        }
        return 'enc:' . $this->encrypt_password(sanitize_text_field($input));
    }

    /**
     * Callback für API Section
     */
    public function api_section_callback() {
        echo '<p>Trage deine Umami-Zugangsdaten ein. Diese werden verwendet um die API zu authentifizieren.</p>';
        echo '<p><strong>API URL:</strong> ' . esc_html($this->api_url) . '</p>';
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
        $value = get_option('umami_username', '');
        ?>
        <input 
            type="text" 
            name="umami_username" 
            value="<?php echo esc_attr($value); ?>" 
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
        $value = get_option('umami_password', '');
        $is_encrypted = strpos($value, 'enc:') === 0;
        ?>
        <input 
            type="password" 
            name="umami_password" 
            value="<?php echo $is_encrypted ? '' : esc_attr($value); ?>" 
            class="regular-text"
            placeholder="<?php echo $is_encrypted ? '••••••••••••••••' : '••••••••'; ?>"
            autocomplete="new-password"
        >
        <p class="description">
            Dein Umami-Passwort (wird verschlüsselt mit WordPress-Salts gespeichert)
            <?php if ($is_encrypted): ?>
                <br><strong style="color: #00a32a;">✓ Passwort ist verschlüsselt gespeichert</strong>
            <?php endif; ?>
        </p>
        <?php
    }

    /**
     * Callback für Website ID Feld
     */
    public function website_id_field_callback() {
        $value = get_option('umami_website_id', '');
        ?>
        <input 
            type="text" 
            name="umami_website_id" 
            value="<?php echo esc_attr($value); ?>" 
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
        $selected_roles = get_option('umami_allowed_roles', array('administrator', 'editor'));
        $wp_roles = wp_roles();

        echo '<fieldset>';
        foreach ($wp_roles->roles as $role_key => $role) {
            $checked = in_array($role_key, $selected_roles) ? 'checked' : '';
            ?>
            <label style="display: block; margin-bottom: 8px;">
                <input type="checkbox" 
                       name="umami_allowed_roles[]" 
                       value="<?php echo esc_attr($role_key); ?>"
                       <?php echo $checked; ?>>
                <?php echo esc_html($role['name']); ?>
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
        $gradient_start = get_option('umami_gradient_start', '#667eea');
        $gradient_end = get_option('umami_gradient_end', '#764ba2');
        $button_text_color = get_option('umami_button_text_color', '#ffffff');
        ?>
        <div style="display: flex; gap: 20px; flex-wrap: wrap;">
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 500;">Start-Farbe</label>
                <input type="color" 
                       name="umami_gradient_start" 
                       id="umami_gradient_start"
                       value="<?php echo esc_attr($gradient_start); ?>"
                       style="width: 80px; height: 40px; cursor: pointer;">
                <div style="margin-top: 3px; font-size: 11px; color: #666;">
                    <?php echo esc_html($gradient_start); ?>
                </div>
            </div>
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 500;">End-Farbe</label>
                <input type="color" 
                       name="umami_gradient_end"
                       id="umami_gradient_end" 
                       value="<?php echo esc_attr($gradient_end); ?>"
                       style="width: 80px; height: 40px; cursor: pointer;">
                <div style="margin-top: 3px; font-size: 11px; color: #666;">
                    <?php echo esc_html($gradient_end); ?>
                </div>
            </div>
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 500;">Text-Farbe</label>
                <input type="color" 
                       name="umami_button_text_color"
                       id="umami_button_text_color" 
                       value="<?php echo esc_attr($button_text_color); ?>"
                       style="width: 80px; height: 40px; cursor: pointer;">
                <div style="margin-top: 3px; font-size: 11px; color: #666;">
                    <?php echo esc_html($button_text_color); ?>
                </div>
            </div>
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 500;">Vorschau</label>
                <div id="umami_button_preview" 
                     style="padding: 10px 20px; 
                            border-radius: 6px; 
                            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
                            background: linear-gradient(135deg, <?php echo esc_attr($gradient_start); ?> 0%, 
                            <?php echo esc_attr($gradient_end); ?> 100%);
                            color: <?php echo esc_attr($button_text_color); ?>;
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
     * Lädt CSS für Dashboard Widget & Analytics Seite
     */
    public function enqueue_styles() {
        $gradient_start = get_option('umami_gradient_start', '#667eea');
        $gradient_end = get_option('umami_gradient_end', '#764ba2');
        $button_text_color = get_option('umami_button_text_color', '#ffffff');

        if (!preg_match('/^#[a-fA-F0-9]{6}$/', $gradient_start)) {
            $gradient_start = '#667eea';
        }
        if (!preg_match('/^#[a-fA-F0-9]{6}$/', $gradient_end)) {
            $gradient_end = '#764ba2';
        }
        if (!preg_match('/^#[a-fA-F0-9]{6}$/', $button_text_color)) {
            $button_text_color = '#ffffff';
        }

        $shadow_color = esc_attr($this->hex_to_rgba($gradient_start, 0.3));
        $shadow_hover = esc_attr($this->hex_to_rgba($gradient_start, 0.4));

        $widget_css = "
            /* Dashboard Widget Styles */
            .umami-widget-content {
                padding: 8px;
            }

            .umami-widget-setup {
                text-align: center;
                padding: 30px 20px;
            }

            .umami-icon-large {
                font-size: 48px;
                width: 48px;
                height: 48px;
                color: #2271b1;
                margin-bottom: 15px;
            }

            .umami-widget-text {
                color: #50575e;
                font-size: 14px;
                margin: 15px 0;
            }

            .umami-widget-main {
                padding: 12px;
            }

            .umami-widget-icon {
                text-align: center;
                margin-bottom: 15px;
            }

            .umami-widget-icon .dashicons {
                font-size: 56px;
                width: 56px;
                height: 56px;
                background: linear-gradient(135deg, " . esc_attr($gradient_start) . " 0%, " . esc_attr($gradient_end) . " 100%);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                background-clip: text;
            }

            .umami-widget-info {
                margin-bottom: 20px;
            }

            .umami-widget-description {
                text-align: center;
                color: #50575e;
                font-size: 13px;
                line-height: 1.5;
                margin: 0;
            }

            .umami-widget-actions {
                display: flex;
                justify-content: center;
            }

            .umami-stats-button {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                padding: 12px 24px;
                text-decoration: none;
                border-radius: 6px;
                font-weight: 500;
                font-size: 14px;
                transition: all 0.2s ease;
                border: none;
                cursor: pointer;
                background: linear-gradient(135deg, " . esc_attr($gradient_start) . " 0%, " . esc_attr($gradient_end) . " 100%);
                color: " . esc_attr($button_text_color) . " !important;
                box-shadow: 0 2px 8px " . $shadow_color . ";
            }

            .umami-stats-button:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 12px " . $shadow_hover . ";
            }

            .umami-stats-button .dashicons {
                font-size: 18px;
                width: 18px;
                height: 18px;
            }

            .umami-widget-actions {
                margin-top: 15px;
            }

            /* Analytics Page Styles */
            .umami-analytics-page {
                margin: 0 0 0 -20px;
                padding: 20px;
                background: #f0f0f1;
            }

            /* Header Bar with Range Selector */
            .umami-header-bar {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 20px;
                padding: 15px 20px;
                background: white;
                border-radius: 8px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                gap: 20px;
                flex-wrap: wrap;
            }

            .umami-page-title {
                display: flex;
                align-items: center;
                gap: 12px;
                margin: 0;
                font-size: 20px;
            }

            .umami-page-title .dashicons {
                font-size: 28px;
                width: 28px;
                height: 28px;
                background: linear-gradient(135deg, " . esc_attr($gradient_start) . " 0%, " . esc_attr($gradient_end) . " 100%);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                background-clip: text;
            }

            .umami-range-selector {
                display: flex;
                gap: 8px;
                background: #f6f7f7;
                padding: 4px;
                border-radius: 8px;
            }

            .umami-range-btn {
                padding: 8px 16px;
                border: none;
                background: transparent;
                color: #50575e;
                font-size: 13px;
                font-weight: 500;
                cursor: pointer;
                border-radius: 6px;
                transition: all 0.2s;
            }

            .umami-range-btn:hover {
                background: #e5e5e5;
            }

            .umami-range-btn.active {
                background: linear-gradient(135deg, " . esc_attr($gradient_start) . " 0%, " . esc_attr($gradient_end) . " 100%);
                color: " . esc_attr($button_text_color) . ";
                box-shadow: 0 2px 8px " . $shadow_color . ";
            }

            .umami-analytics-setup {
                text-align: center;
                padding: 80px 20px;
                max-width: 600px;
                margin: 0 auto;
                background: white;
                border-radius: 8px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            }

            .umami-icon-xlarge {
                font-size: 80px;
                width: 80px;
                height: 80px;
                background: linear-gradient(135deg, " . esc_attr($gradient_start) . " 0%, " . esc_attr($gradient_end) . " 100%);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                background-clip: text;
                margin-bottom: 20px;
            }

            .umami-analytics-setup h2 {
                color: #1d2327;
                margin-bottom: 15px;
            }

            .umami-analytics-setup p {
                color: #50575e;
                font-size: 15px;
                margin-bottom: 25px;
            }

            .umami-loading {
                text-align: center;
                padding: 60px 20px;
                background: white;
                border-radius: 8px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            }

            .umami-loading p {
                margin-top: 15px;
                color: #50575e;
            }

            .umami-error {
                text-align: center;
                padding: 60px 20px;
                background: white;
                border-radius: 8px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            }

            .umami-error .dashicons {
                font-size: 48px;
                width: 48px;
                height: 48px;
                color: #d63638;
            }

            .umami-error p {
                margin-top: 15px;
                color: #50575e;
            }

            /* Metrics Header */
            .umami-metrics-header {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 15px;
                margin-bottom: 20px;
                background: white;
                padding: 20px;
                border-radius: 8px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            }

            .umami-metrics-header .umami-metric-card {
                text-align: center;
                padding: 0;
                border-right: 1px solid #f0f0f1;
            }

            .umami-metrics-header .umami-metric-card:last-child {
                border-right: none;
            }

            .umami-metrics-header .umami-metric-label {
                font-size: 12px;
                color: #646970;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                margin-bottom: 8px;
            }

            .umami-metrics-header .umami-metric-value {
                font-size: 24px;
                font-weight: 700;
                color: #1d2327;
            }

            /* Timeline Chart */
            .umami-timeline-chart {
                background: white;
                padding: 20px;
                border-radius: 8px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                margin-bottom: 20px;
                height: 300px;
                position: relative;
            }

            .umami-timeline-chart canvas {
                max-height: 100%;
            }

            /* DEVICE CARDS - Prominent! */
            .umami-device-cards {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 20px;
                margin-bottom: 20px;
            }

            .umami-device-card {
                background: white;
                padding: 24px;
                border-radius: 12px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.08);
                transition: all 0.3s ease;
                position: relative;
                overflow: hidden;
            }

            .umami-device-card:hover {
                transform: translateY(-4px);
                box-shadow: 0 4px 16px rgba(0,0,0,0.12);
            }

            .umami-device-icon {
                font-size: 48px;
                margin-bottom: 16px;
                background: linear-gradient(135deg, " . esc_attr($gradient_start) . " 0%, " . esc_attr($gradient_end) . " 100%);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                background-clip: text;
            }

            .umami-device-icon i {
                display: block;
            }

            .umami-device-info {
                margin-bottom: 16px;
            }

            .umami-device-label {
                font-size: 14px;
                color: #646970;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                font-weight: 600;
                margin-bottom: 8px;
            }

            .umami-device-stats {
                display: flex;
                align-items: baseline;
                gap: 12px;
            }

            .umami-device-count {
                font-size: 32px;
                font-weight: 700;
                color: #1d2327;
            }

            .umami-device-percentage {
                font-size: 18px;
                font-weight: 600;
                color: " . esc_attr($gradient_start) . ";
            }

            .umami-device-bar {
                height: 6px;
                background: #f0f0f1;
                border-radius: 3px;
                overflow: hidden;
            }

            .umami-device-bar-fill {
                height: 100%;
                background: linear-gradient(90deg, " . esc_attr($gradient_start) . " 0%, " . esc_attr($gradient_end) . " 100%);
                border-radius: 3px;
                transition: width 0.6s ease;
            }

            /* Two Column Layout */
            .umami-two-column {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 20px;
                margin-bottom: 20px;
            }

            .umami-column {
                display: flex;
                flex-direction: column;
                gap: 20px;
            }

            .umami-section {
                background: white;
                padding: 24px;
                border-radius: 8px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            }

            .umami-section-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 20px;
            }

            .umami-section-content {
                /* Content area */
            }

            .umami-view-switcher {
                display: flex;
                gap: 8px;
                background: #f6f7f7;
                padding: 4px;
                border-radius: 6px;
            }

            .umami-view-switcher i {
                padding: 6px 10px;
                font-size: 16px;
                color: #646970;
                cursor: pointer;
                border-radius: 4px;
                transition: all 0.2s;
            }

            .umami-view-switcher i:hover {
                background: #e5e5e5;
                color: " . esc_attr($gradient_start) . ";
            }

            .umami-view-switcher i.active {
                background: linear-gradient(135deg, " . esc_attr($gradient_start) . " 0%, " . esc_attr($gradient_end) . " 100%);
                color: " . esc_attr($button_text_color) . ";
                box-shadow: 0 2px 4px " . $shadow_color . ";
            }

            .umami-chart-container {
                position: relative;
                width: 100%;
            }

            .umami-chart-container canvas {
                max-height: 100%;
            }

            .umami-section h3 {
                margin: 0;
                font-size: 14px;
                font-weight: 600;
                color: #1d2327;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .umami-section h3 i {
                color: " . esc_attr($gradient_start) . ";
                font-size: 16px;
            }

            /* Lists with Progress Bars */
            .umami-list {
                display: flex;
                flex-direction: column;
                gap: 16px;
            }

            .umami-list-item {
                display: flex;
                flex-direction: column;
                gap: 8px;
            }

            .umami-list-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .umami-list-label {
                font-size: 13px;
                color: #50575e;
                font-weight: 500;
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .umami-list-icon {
                font-size: 16px;
                color: #646970;
                flex-shrink: 0;
            }

            .umami-favicon {
                width: 16px;
                height: 16px;
                margin-right: 8px;
                vertical-align: middle;
            }

            .umami-referrer-link {
                color: #50575e;
                text-decoration: none;
                transition: color 0.2s;
            }

            .umami-referrer-link:hover {
                color: " . esc_attr($gradient_start) . ";
                text-decoration: underline;
            }

            .umami-flag {
                font-size: 18px;
                margin-right: 4px;
            }

            .umami-list-value {
                font-size: 14px;
                font-weight: 700;
                color: #1d2327;
            }

            .umami-progress-bar {
                height: 8px;
                background: #f0f0f1;
                border-radius: 4px;
                overflow: hidden;
            }

            .umami-progress-fill {
                height: 100%;
                background: linear-gradient(90deg, " . esc_attr($gradient_start) . " 0%, " . esc_attr($gradient_end) . " 100%);
                border-radius: 4px;
                transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
            }

            /* Pages List (simple table-like) */
            .umami-pages-list {
                display: flex;
                flex-direction: column;
            }

            .umami-page-item {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 12px 0;
                border-bottom: 1px solid #f0f0f1;
            }

            .umami-page-item:last-child {
                border-bottom: none;
            }

            .umami-page-item:hover {
                background-color: #f9f9f9;
            }

            .umami-page-path {
                flex: 1;
                font-size: 13px;
                color: #50575e;
                word-break: break-word;
                padding-right: 15px;
            }

            .umami-page-stats {
                display: flex;
                gap: 15px;
                align-items: center;
                white-space: nowrap;
            }

            .umami-page-count {
                font-size: 14px;
                font-weight: 700;
                color: #1d2327;
                min-width: 50px;
                text-align: right;
            }

            .umami-page-percent {
                font-size: 13px;
                color: #646970;
                min-width: 40px;
                text-align: right;
            }

            /* Tables (deprecated but kept for compatibility) */
            .umami-table {
                width: 100%;
                border-collapse: collapse;
            }

            .umami-table tr {
                border-bottom: 1px solid #f0f0f1;
            }

            .umami-table tr:last-child {
                border-bottom: none;
            }

            .umami-table tr:hover {
                background-color: #f9f9f9;
            }

            .umami-table td {
                padding: 10px 0;
            }

            .umami-table-label {
                font-size: 13px;
                color: #50575e;
                word-break: break-word;
            }

            .umami-table-value {
                text-align: right;
                font-weight: 600;
                color: #1d2327;
                font-size: 14px;
                white-space: nowrap;
            }

            /* Responsive */
            @media screen and (max-width: 1200px) {
                .umami-two-column {
                    grid-template-columns: 1fr;
                }
            }

            @media screen and (max-width: 782px) {
                .umami-analytics-page {
                    margin-left: 0;
                    padding: 15px;
                }

                .umami-header-bar {
                    flex-direction: column;
                    align-items: flex-start;
                }

                .umami-range-selector {
                    width: 100%;
                    overflow-x: auto;
                }

                .umami-range-btn {
                    font-size: 12px;
                    padding: 6px 12px;
                    white-space: nowrap;
                }

                .umami-metrics-header {
                    grid-template-columns: repeat(2, 1fr);
                    gap: 10px;
                    padding: 15px;
                }

                .umami-metrics-header .umami-metric-card {
                    border-right: none;
                    border-bottom: 1px solid #f0f0f1;
                    padding-bottom: 10px;
                }

                .umami-metrics-header .umami-metric-card:nth-last-child(-n+2) {
                    border-bottom: none;
                    padding-bottom: 0;
                }

                .umami-metrics-header .umami-metric-value {
                    font-size: 20px;
                }

                .umami-device-cards {
                    grid-template-columns: 1fr;
                }

                .umami-device-count {
                    font-size: 28px;
                }

                .umami-device-percentage {
                    font-size: 16px;
                }

                .umami-section-header {
                    flex-direction: column;
                    align-items: flex-start;
                    gap: 12px;
                }

                .umami-view-switcher {
                    align-self: flex-end;
                }
            }

            @media screen and (max-width: 600px) {
                .umami-device-icon {
                    font-size: 36px;
                }
            }
        ";

        wp_register_style('umami-stats-custom', false);
        wp_add_inline_style('umami-stats-custom', $widget_css);
        wp_enqueue_style('umami-stats-custom');
    }

    /**
     * Konvertiert HEX zu RGBA
     */
    private function hex_to_rgba($hex, $alpha) {
        $hex = str_replace('#', '', $hex);

        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        return "rgba({$r}, {$g}, {$b}, {$alpha})";
    }
}

// Plugin initialisieren
new Umami_Stats_Viewer();
