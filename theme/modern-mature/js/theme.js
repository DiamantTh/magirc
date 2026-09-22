var menu;
function mmChartColors() {
    var css = getComputedStyle(document.documentElement);
    return [
        css.getPropertyValue('--chart-1').trim(),
        css.getPropertyValue('--chart-2').trim(),
        css.getPropertyValue('--chart-3').trim(),
        css.getPropertyValue('--chart-4').trim(),
        css.getPropertyValue('--chart-5').trim(),
        css.getPropertyValue('--chart-6').trim()
    ];
}
function mmShadeColor(hex, percent) {
    var color = hex.replace('#', '');
    if (color.length === 3) {
        color = color.split('').map(function(c){ return c + c; }).join('');
    }
    var num = parseInt(color, 16);
    var r = (num >> 16) & 255;
    var g = (num >> 8) & 255;
    var b = num & 255;
    var t = percent < 0 ? 0 : 255;
    var p = Math.abs(percent);
    var R = Math.round((t - r) * p) + r;
    var G = Math.round((t - g) * p) + g;
    var B = Math.round((t - b) * p) + b;
    return 'rgb(' + R + ',' + G + ',' + B + ')';
}
function mmEchartsBaseOptions() {
    var css = getComputedStyle(document.documentElement);
    var bg1 = css.getPropertyValue('--chart-bg-1').trim();
    var bg2 = css.getPropertyValue('--chart-bg-2').trim();
    return {
        animationDuration: 650,
        animationDurationUpdate: 450,
        animationEasing: 'cubicOut',
        animationEasingUpdate: 'cubicOut',
        color: mmChartColors(),
        backgroundColor: {
            type: 'linear',
            x: 0,
            y: 0,
            x2: 1,
            y2: 1,
            colorStops: [
                { offset: 0, color: bg1 },
                { offset: 1, color: bg2 }
            ]
        },
        textStyle: {
            fontFamily: css.getPropertyValue('--font-body').trim(),
            color: css.getPropertyValue('--text').trim()
        },
        title: { textStyle: { color: css.getPropertyValue('--text').trim() } },
        legend: { textStyle: { color: css.getPropertyValue('--text').trim(), fontWeight: 600, textShadowColor: 'rgba(0,0,0,0.35)', textShadowBlur: 3 } },
        tooltip: {
            trigger: 'axis',
            backgroundColor: css.getPropertyValue('--surface').trim(),
            borderColor: css.getPropertyValue('--border').trim(),
            textStyle: { color: css.getPropertyValue('--text').trim() },
            extraCssText: 'box-shadow: 0 8px 20px rgba(0,0,0,0.18);',
            axisPointer: {
                lineStyle: { color: css.getPropertyValue('--accent').trim(), width: 1 }
            }
        },
        grid: { left: 40, right: 24, top: 30, bottom: 32, containLabel: true },
        xAxis: {
            axisLine: { lineStyle: { color: css.getPropertyValue('--border').trim() } },
            axisTick: { lineStyle: { color: css.getPropertyValue('--border').trim() } },
            axisLabel: { color: css.getPropertyValue('--muted').trim(), fontWeight: 600 },
            splitLine: { lineStyle: { color: css.getPropertyValue('--border').trim() } }
        },
        yAxis: {
            axisLine: { lineStyle: { color: css.getPropertyValue('--border').trim() } },
            axisTick: { lineStyle: { color: css.getPropertyValue('--border').trim() } },
            axisLabel: { color: css.getPropertyValue('--muted').trim(), fontWeight: 600 },
            splitLine: { lineStyle: { color: css.getPropertyValue('--border').trim() } }
        },
        series: {
            label: {
                color: css.getPropertyValue('--text').trim(),
                fontWeight: 600,
                textShadowColor: 'rgba(0,0,0,0.45)',
                textShadowBlur: 4
            },
            itemStyle: {
                borderColor: 'rgba(255,255,255,0.06)',
                borderWidth: 1
            }
        }
    };
}
function mmEchartsInit(id) {
    if (!window.echarts) return null;
    var el = document.getElementById(id);
    if (!el) return null;
    var chart = echarts.init(el, null, { renderer: 'canvas' });
    chart.setOption(mmEchartsBaseOptions());
    return chart;
}
function mmResizeCharts(container) {
    if (!window.echarts) return;
    var $scope = container ? $(container) : $(document);
    $scope.find('div[id^="chart_"], div[id*="chart-"]').each(function() {
        var inst = echarts.getInstanceByDom(this);
        if (inst) inst.resize();
    });
}
function mmInitMenu() {
    menu = $('#chanmenu');
    if (!menu.length) return;
    menu.addClass('mm-menu').hide().css({ position: 'absolute', zIndex: 10 });
    menu.on('click', 'a', function(event) {
        event.preventDefault();
    });
    menu.on('click', 'li', function(event) {
        event.preventDefault();
        var chan = encodeURIComponent(menu.data('channel') || '');
        switch ($(this).data('action')) {
            case 'irc':
                location.href = 'irc://'+net_roundrobin+':'+net_port+'/'+chan.replace('%23', '');
                break;
            case 'ircs':
                location.href = 'ircs://'+net_roundrobin+':'+net_port_ssl+'/'+chan.replace('%23', '');
                break;
            case 'webchat':
                location.href = service_webchat + chan;
                break;
            case 'webchat2':
                location.href = service_webchat + menu.data('channel');
                break;
            case 'mibbit':
                location.href = 'http://widget.mibbit.com/?settings='+service_mibbit+'&server='+net_roundrobin+'&channel='+chan+'&promptPass=true';
                break;
        }
        menu.hide();
    });
}

function mmPositionMenu(el) {
    var offset = $(el).offset();
    if (!offset) return;
    var left = offset.left + $(el).outerWidth() - menu.outerWidth();
    var top = offset.top + $(el).outerHeight() + 6;
    menu.css({ left: left, top: top });
}

function mmInitTabs(selector) {
    var $tabs = $(selector);
    if (!$tabs.length) return null;
    var $list = $tabs.children('ul');
    var $links = $list.find('a');
    $tabs.addClass('mm-tabs');
    $list.addClass('mm-tab-list');
    $links.each(function(i) {
        var $a = $(this);
        var title = $a.closest('li').attr('title') || ('tab'+i);
        var panelId = 'mm-tab-' + title;
        if (!$tabs.find('#' + panelId).length) {
            $tabs.append('<div id="'+panelId+'" class="mm-tab-panel"></div>');
        }
        $a.attr('data-panel', panelId);
    });
    function activate($a, updateHash) {
        var $li = $a.closest('li');
        if ($li.hasClass('mm-tab-disabled')) return;
        $list.find('li').removeClass('mm-tab-active');
        $li.addClass('mm-tab-active');
        var panelId = $a.data('panel');
        $tabs.find('.mm-tab-panel').hide();
        var $panel = $tabs.find('#' + panelId).show();
        if (!$a.data('loaded')) {
            $.get($a.attr('href'), function(result) {
                var $tmp = $('<div></div>').html(result);
                var $scripts = $tmp.find('script');
                $scripts.remove();
                $panel.html($tmp.html());
                $scripts.each(function() {
                    var src = $(this).attr('src');
                    if (src) {
                        $.getScript(src);
                    } else {
                        $.globalEval($(this).text());
                    }
                });
                setTimeout(function() { mmResizeCharts($panel); }, 50);
                $a.data('loaded', true);
            }).fail(function() {
                $panel.text(mLang.LoadError);
            });
        }
        if (updateHash && $li.attr('title')) {
            window.location.hash = $li.attr('title');
        }
        setTimeout(function() { mmResizeCharts($panel); }, 50);
    }
    $links.on('click', function(event) {
        event.preventDefault();
        activate($(this), true);
    });
    var initial = null;
    if (window.location.hash) {
        var title = window.location.hash.substring(1);
        initial = $list.find("li[title='"+title+"'] a");
    }
    if (!initial || !initial.length) {
        initial = $links.first();
    }
    activate(initial, false);
    return {
        disable: function(index) {
            $list.find('li').eq(index).addClass('mm-tab-disabled');
        }
    };
}

function mmBuildTableToolbar(settings) {
    var $toolbar = $('<div class="mm-table-toolbar"></div>');
    var $left = $('<div class="mm-table-left"></div>');
    var $right = $('<div class="mm-table-right"></div>');
    var hasControls = false;
    if (settings.lengthChange) {
        var $select = $('<select class="mm-table-length-select"></select>');
        var lengths = settings.lengthMenu || [10, 25, 50, 100];
        $.each(lengths, function(_, val) {
            var $opt = $('<option></option>').attr('value', val).text(val);
            if (val === settings.pageLength) $opt.attr('selected', 'selected');
            $select.append($opt);
        });
        var lengthText = (mLang.DataTables && mLang.DataTables.lengthMenu) || 'Show _MENU_ entries';
        var parts = lengthText.split('_MENU_');
        var $label = $('<label class="mm-table-length"></label>');
        $label.append(document.createTextNode(parts[0] || ''));
        $label.append($select);
        $label.append(document.createTextNode(parts[1] || ''));
        $left.append($label);
        hasControls = true;
    }
    if (settings.searching) {
        var searchText = (mLang.DataTables && mLang.DataTables.search) || 'Search:';
        var $input = $('<input type="search" class="mm-table-search-input" />');
        var $labelSearch = $('<label class="mm-table-search"></label>');
        $labelSearch.append(document.createTextNode(searchText + ' '));
        $labelSearch.append($input);
        $right.append($labelSearch);
        hasControls = true;
    }
    if (!hasControls) return null;
    $toolbar.append($left).append($right);
    return $toolbar;
}

function mmInitTable(selector, options) {
    var $table = $(selector);
    if (!$table.length) return null;
    $table.addClass('mm-table');
    var settings = $.extend({
        pageLength: 25,
        searching: true,
        info: true,
        lengthChange: true,
        paging: true,
        ordering: true,
        order: null,
        ajax: null,
        columns: []
    }, options || {});
    var state = {
        data: [],
        filtered: [],
        page: 0,
        sortIndex: settings.order ? settings.order[0][0] : null,
        sortDir: settings.order ? settings.order[0][1] : 'asc',
        search: ''
    };
    var $wrap = $('<div class="mm-table-wrap"></div>');
    $table.before($wrap);
    $wrap.append($table);
    var $toolbar = mmBuildTableToolbar(settings);
    var $info = $('<div class="mm-table-info"></div>');
    var $pagination = $('<div class="mm-table-pagination"></div>');
    if ($toolbar) $wrap.prepend($toolbar);
    $wrap.append($info).append($pagination);
    var $searchInput = $toolbar ? $toolbar.find('.mm-table-search-input') : $();
    var $lengthSelect = $toolbar ? $toolbar.find('.mm-table-length-select') : $();

    function textFromHtml(html) {
        return $('<div>').html(html).text();
    }

    function getCellValue(row, colDef, meta) {
        var value = colDef.data ? row[colDef.data] : '';
        if (colDef.render) {
            return colDef.render(value, 'display', row, meta);
        }
        return value == null ? '' : value;
    }

    function getSortValue(row, colDef, meta) {
        var raw = colDef.data ? row[colDef.data] : '';
        if (colDef.render) {
            raw = textFromHtml(colDef.render(raw, 'sort', row, meta));
        }
        if (raw == null) return '';
        var num = parseFloat(raw);
        if (!isNaN(num) && (''+raw).match(/^[\d\.\-]+$/)) return num;
        return (''+raw).toLowerCase();
    }

    function applySearch() {
        var term = state.search.toLowerCase();
        if (!term) {
            state.filtered = state.data.slice();
            return;
        }
        state.filtered = $.grep(state.data, function(row) {
            var parts = [];
            $.each(settings.columns, function(i, colDef) {
                var meta = { row: 0, col: i };
                var html = getCellValue(row, colDef, meta);
                parts.push(textFromHtml(html));
            });
            return parts.join(' ').toLowerCase().indexOf(term) !== -1;
        });
    }

    function applySort() {
        if (!settings.ordering || state.sortIndex === null) return;
        var colDef = settings.columns[state.sortIndex];
        state.filtered.sort(function(a, b) {
            var av = getSortValue(a, colDef, { col: state.sortIndex });
            var bv = getSortValue(b, colDef, { col: state.sortIndex });
            if (av < bv) return state.sortDir === 'asc' ? -1 : 1;
            if (av > bv) return state.sortDir === 'asc' ? 1 : -1;
            return 0;
        });
    }

    function renderRows() {
        var $tbody = $table.find('tbody');
        $tbody.empty();
        var total = state.filtered.length;
        var start = 0;
        var end = total;
        if (settings.paging) {
            start = state.page * settings.pageLength;
            end = Math.min(start + settings.pageLength, total);
        }
        if (total === 0) {
            $tbody.append('<tr><td colspan="'+settings.columns.length+'">'+(mLang.DataTables && mLang.DataTables.emptyTable ? mLang.DataTables.emptyTable : 'No data available in table')+'</td></tr>');
            updateInfo(0, 0, 0);
            updatePagination(0);
            return;
        }
        for (var i = start; i < end; i++) {
            var row = state.filtered[i];
            var $tr = $('<tr></tr>');
            if (row.DT_RowId) $tr.attr('id', row.DT_RowId);
            $.each(settings.columns, function(colIndex, colDef) {
                var html = getCellValue(row, colDef, { row: i, col: colIndex });
                var $td = $('<td></td>').html(html);
                $tr.append($td);
            });
            $tbody.append($tr);
        }
        updateInfo(start + 1, end, total);
        updatePagination(total);
    }

    function updateInfo(start, end, total) {
        if (!settings.info) { $info.hide(); return; }
        $info.show();
        var infoText = (mLang.DataTables && mLang.DataTables.info) || 'Showing _START_ to _END_ of _TOTAL_ entries';
        $info.text(infoText.replace('_START_', start).replace('_END_', end).replace('_TOTAL_', total));
    }

    function updatePagination(total) {
        if (!settings.paging) { $pagination.hide(); return; }
        $pagination.show();
        var pages = Math.ceil(total / settings.pageLength);
        var $first = $('<button type="button" class="mm-page-btn">'+((mLang.DataTables && mLang.DataTables.paginate && mLang.DataTables.paginate.first) || 'First')+'</button>');
        var $prev = $('<button type="button" class="mm-page-btn">'+((mLang.DataTables && mLang.DataTables.paginate && mLang.DataTables.paginate.previous) || 'Previous')+'</button>');
        var $next = $('<button type="button" class="mm-page-btn">'+((mLang.DataTables && mLang.DataTables.paginate && mLang.DataTables.paginate.next) || 'Next')+'</button>');
        var $last = $('<button type="button" class="mm-page-btn">'+((mLang.DataTables && mLang.DataTables.paginate && mLang.DataTables.paginate.last) || 'Last')+'</button>');
        $pagination.empty().append($first, $prev);
        for (var i = 0; i < pages; i++) {
            var $num = $('<button type="button" class="mm-page-btn">'+(i+1)+'</button>');
            if (i === state.page) $num.addClass('is-active');
            (function(page) {
                $num.on('click', function() {
                    state.page = page;
                    renderRows();
                });
            })(i);
            $pagination.append($num);
        }
        $pagination.append($next, $last);
        $first.prop('disabled', state.page === 0);
        $prev.prop('disabled', state.page === 0);
        $next.prop('disabled', state.page >= pages - 1);
        $last.prop('disabled', state.page >= pages - 1);
        $first.on('click', function(){ state.page = 0; renderRows(); });
        $prev.on('click', function(){ state.page = Math.max(0, state.page - 1); renderRows(); });
        $next.on('click', function(){ state.page = Math.min(pages - 1, state.page + 1); renderRows(); });
        $last.on('click', function(){ state.page = pages - 1; renderRows(); });
    }

    function loadData(url) {
        if (!url) return;
        $.getJSON(url, function(result) {
            state.data = result.data || result || [];
            state.page = 0;
            applySearch();
            applySort();
            renderRows();
        });
    }

    function refresh() {
        applySearch();
        applySort();
        renderRows();
    }

    $searchInput.on('input', function() {
        state.search = $(this).val();
        state.page = 0;
        refresh();
    });
    $lengthSelect.on('change', function() {
        settings.pageLength = parseInt($(this).val(), 10) || settings.pageLength;
        state.page = 0;
        renderRows();
    });

    if (settings.ordering) {
        $table.find('thead th').each(function(index) {
            $(this).addClass('mm-sortable').on('click', function() {
                if (state.sortIndex === index) {
                    state.sortDir = state.sortDir === 'asc' ? 'desc' : 'asc';
                } else {
                    state.sortIndex = index;
                    state.sortDir = 'asc';
                }
                refresh();
            });
        });
    }

    if (settings.ajax) {
        loadData(settings.ajax);
    } else {
        state.data = [];
        $table.find('tbody tr').each(function() {
            var row = { DT_RowId: $(this).attr('id') || null };
            settings.columns.forEach(function(colDef, idx) {
                row[colDef.data || ('col'+idx)] = $(this).find('td').eq(idx).text();
            }, this);
            state.data.push(row);
        });
        refresh();
    }

    return {
        reload: function() { loadData(settings.ajax); },
        setUrl: function(url) { settings.ajax = url; loadData(settings.ajax); }
    };
}

$(document).ready(function() {
    $("#loading").ajaxStart(function(){
        $(this).show();
    }).ajaxStop(function(){
        $(this).hide();
    });
    $("#locale").change(function() {
        Cookies.set("magirc_locale", $("#locale").val(), { expires: 30, path: '/' });
        window.location.reload();
    });
    mmInitMenu();
});

function openChanMenu(element) {
    if (!menu || !menu.length) return false;
    menu.data('channel', $(element).closest('tr').attr('id'));
    if (menu.is(':visible')) {
        menu.hide();
        return false;
    }
    menu.show();
    mmPositionMenu(element);
    $(document).one("click", function() { menu.hide(); });
    return false;
}

function getUserStatus(user) {
    if (user['away']) return '<img src="theme/'+theme+'/img/status/user-away.png" alt="away" title="'+mLang.AwayAs+' '+user['nickname']+'" \/>';
    else if (user['online']) return '<img src="theme/'+theme+'/img/status/user-online.png" alt="online" title="'+mLang.OnlineAs+' '+user['nickname']+'" \/>';
    else return '<img src="theme/'+theme+'/img/status/user-offline.png" alt="offline" title="'+mLang.Offline+'" \/>';
}
function getUserExtra(user) {
    var out = '';
    if (user['bot']) out += ' <img src="theme/'+theme+'/img/status/bot.png" alt="bot" title="'+mLang.Bot+'" \/>';
    if (user['service']) out += ' <img src="theme/'+theme+'/img/status/service.png" alt="service" title="'+mLang.Service+'" \/>';
    if (user['operator']) out += ' <img src="theme/'+theme+'/img/status/operator.png" alt="oper" title="'+user['operator_level']+'" \/>';
    if (user['helper']) out += ' <img src="theme/'+theme+'/img/status/help.png" alt="help" title="'+mLang.Helper+'" \/>';
    return out;
}
function getChannelLinks() {
    if (net_roundrobin || service_webchat) {
        return '<button type="button" title="'+mLang.Join+'..." class="chanbutton" aria-label="'+mLang.Join+'"><span class="chanbutton-caret"></span></button>';
    } else {
        return '';
    }
}
