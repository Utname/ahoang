$(function () {
    $(document).on('shown.bs.dropdown', '.table .dropdown', function () {
        var $btn = $(this).find('.dropdown-toggle');
        var $menu = $(this).find('.dropdown-menu');
        if ($btn.length && $menu.length) {
            var rect = $btn[0].getBoundingClientRect();
            var menuWidth = $menu.outerWidth();
            var top = rect.bottom + 2;
            var left = rect.right - menuWidth;
            if (left < 10) left = 10;
            var menuHeight = $menu.outerHeight();
            if (top + menuHeight > $(window).height() - 10 && rect.top - menuHeight > 10) {
                top = rect.top - menuHeight - 2;
            }
            $menu.css({
                'position': 'fixed',
                'top': top + 'px',
                'left': left + 'px',
                'right': 'auto',
                'bottom': 'auto',
                'z-index': 1030,
                'margin': 0
            });
        }
    }).on('hide.bs.dropdown hidden.bs.dropdown', '.table .dropdown', function () {
        var $menu = $(this).find('.dropdown-menu');
        $menu.css({
            'position': '',
            'top': '',
            'left': '',
            'right': '',
            'bottom': '',
            'z-index': '',
            'margin': ''
        });
    });

    $(document).on('show.bs.modal', function () {
        $('.table .dropdown.open').removeClass('open');
        $('.table .dropdown-menu').css({
            'position': '',
            'top': '',
            'left': '',
            'right': '',
            'bottom': '',
            'z-index': '',
            'margin': ''
        });
    });

    $(window).on('scroll', function () {
        if (!$('body').hasClass('modal-open')) {
            $('.table .dropdown.open').removeClass('open');
        }
    });
    $(document).on('scroll', '.mosaic-list-table-wrap', function () {
        $('.table .dropdown.open').removeClass('open');
    });

    $("body").on('click', "a.ajax", function (event) {
        event.preventDefault();

        if (!$(this).hasClass('with-confirm')) {
            ajaxGet(this.href, false);
        }
    });

    $("body").on('click', "a.ajax-modal", function (event) {
        event.preventDefault();

        $('.table .dropdown.open').removeClass('open');
        $('.table .dropdown-menu').css({
            'position': '',
            'top': '',
            'left': '',
            'right': '',
            'bottom': '',
            'z-index': '',
            'margin': ''
        });

        if (!$(this).hasClass('with-confirm')) {
            ajaxGet(this.href, false);
        }

        if ($(this).data('target') && $($(this).data('target')).length) {
            $($(this).data('target')).modal('show');
        }

        $('.tooltip.fade').remove();
    });

    $("body").on('submit', "form.ajax", function (event) {
        event.preventDefault();

        var _this = $(this);
        var submitButton = $(this).find("[type=submit][clicked=true]");

        var form = $(this)[0];
        var modal = _this.closest(".modal");
        var formData = new FormData(form);
        if (submitButton.length) {
            formData.append(submitButton.attr('name'), '1');
        }

        ajaxFormFile(this.action, formData, function (payload) {
            if ((typeof payload.doNotCloseModal === 'undefined' || !payload.doNotCloseModal) && _this.hasClass('ajax-modal-close')) {
                modal.modal('hide');
            }

            if (_this.data('callback')) {
                window[_this.data('callback')](payload);
            }
        })
    });

    $("body").on('click', "form.ajax [type=submit]", function () {
        $("input[type=submit]", $(this).parents("form")).removeAttr("clicked");
        $(this).attr("clicked", "true");
    });

    $("body").on('click', "a.with-confirm", function (event) {
        event.preventDefault();

        var link = $(this);

        alertify.confirm(link.data('message'), function (e) {
            if (e) {
                if (link.hasClass('ajax')) {
                    ajaxGet(link.attr('href'), false);
                }
                if (!link.hasClass('ajax')) {
                    location.href = link.attr('href');
                }
            }
        });
    });

    $("body").on('click', "button.with-confirm", function (event) {
        event.preventDefault();

        var button = $(this);
        var form = button.closest("form");

        alertify.confirm(button.data('message'), function (e) {
            if (e) {
                //workaround for nette $form->isSubmittedBy()
                button.removeClass('with-confirm')
                button.click();
            }
        });
    });

    $("body").on('click', ".row-as-link:has(a.row-as-link-target) td:not(.row-as-link-ignore)", function (event) {
        if (!$(event.target).hasClass('row-as-link-ignore') && $(event.target).closest(".row-as-link-ignore").length === 0) {
            event.preventDefault();

            var trigger = $(this).closest("tr").find("a.row-as-link-target");
            if (trigger.hasClass('ajax') || trigger.hasClass('ajax-modal')) {
                trigger.trigger('click');
            }
            else {
                if (trigger.attr('target') == '_blank') {
                    window.open(trigger.attr('href'), '_blank');
                }
                else {
                    location.href = trigger.attr('href');
                }
            }
        }
    });

    $("body").on('click', ".row-as-label td", function (event) {
        if (!$(event.target).hasClass('row-as-label-ignore')) {
            event.preventDefault();

            $(this).closest("tr").find("input[type=checkbox]").trigger('click');
        }
    });

    $("body").on('click', ".stop-propagation", function (event) {
        event.stopPropagation();
    });

    $("body").on('shown.bs.modal', ".modal", function (e) {
        var zIndex = 11001;
        $(".modal.in").each(function() {
            var style = window.getComputedStyle($(this)[0]);
            var modalZindex = parseInt(style.getPropertyValue('z-index'));
            if (modalZindex !== null && modalZindex >= zIndex) {
                zIndex = modalZindex + 2;
            }
        })
        $(this).css('z-index', zIndex);
        initSelect2();
        $(window).trigger('resize');
    })

    $("body").on('hidden.bs.modal', ".modal", function (e) {
        if ($(".modal.in").length) {
            $("body").addClass('modal-open');
        }
    })

    $("body").on('shown.bs.tab', "a[data-toggle='tab']", function (e) {
        $(window).trigger('resize');
    })

    $("body").on('change', ".table .filters > *", function (event) {
        if(($(this).find('input').length > 0 && !$(this).find('input').hasClass('ignore'))
            || $(this).find('select').length > 0 && !$(this).find('select').hasClass('ignore')){
            $(this).closest("form").submit();
        }
    });

    $("body").on('change', ".filters-container > *, .filters-view-container > *", function (event) {
        $(this).closest("form").submit();
    });

    $("body").on('change', ".submit-on-change input", function (e) {

        e.preventDefault();
        var loadLink = $(this);
        loadLink.before("<i class='custom-checked-ajax fa-spin'></i>");
        var loadLinkicon = loadLink.parent().find('i');
        loadLinkicon.addClass('fa-spin')

        var valueInput = $(this).attr("type") != "checkbox" ? $(this).val() : ($(this).is(':checked') ? 1 : 0);
        var branchId = $("select[name='custom__branches']").val();
        var currencyId = $("select[name='custom__currency']").val();

        ajaxGet("?do=updatePrices&item="+$(this).attr("name")+"&itemId="+$(this).attr("data-item-id")+"&value="+valueInput+"&branchId="+branchId+"&currencyId="+currencyId, function(data){

            loadLinkicon.removeClass('fa-spin')
            var currentIconClass = loadLinkicon.attr('class');

            if(data.data !== false){
                loadLinkicon.attr('class', 'fa fa-check text-success custom-checked-ajax pull-right');
            }
            else{
                loadLinkicon.attr('class', 'fa fa-times text-danger custom-checked-ajax pull-right');
            }

            setTimeout(function(e){
                //loadLink.parent().find(".custom-checked-ajax").empty().remove();
            }, 1000);
        });
    });

    // Products list: show save button when any price/date field changes
    $("body").on('change', ".product-price-fields input, .product-date-fields input", function(){
        var row = $(this).closest('tr');
        row.find('.btn-save-prices').removeClass('hidden');
    });

    // Products list: save all four values with one request
    $("body").on('click', ".btn-save-prices", function(e){
        e.preventDefault();
        var btn = $(this);
        var row = btn.closest('tr');
        var productsId = btn.data('products-id');
        var price = row.find("input[name='price__"+productsId+"']").val();
        var price_action = row.find("input[name='price_action__"+productsId+"']").val();
        var action_from = row.find("input[name='action_from__"+productsId+"']").val();
        var action_to = row.find("input[name='action_to__"+productsId+"']").val();
        var itemId = row.find("input[name='price__"+productsId+"']").data('item-id') || '';
        var branchId = $("select[name='custom__branches']").val();
        var currencyId = $("select[name='custom__currency']").val();

        btn.prop('disabled', true).addClass('loading');

        var url = "?do=updatePricesAll"
            + "&itemId=" + encodeURIComponent(itemId)
            + "&productsId=" + encodeURIComponent(productsId)
            + "&price=" + encodeURIComponent(price)
            + "&price_action=" + encodeURIComponent(price_action)
            + "&action_from=" + encodeURIComponent(action_from)
            + "&action_to=" + encodeURIComponent(action_to)
            + "&branchId=" + encodeURIComponent(branchId)
            + "&currencyId=" + encodeURIComponent(currencyId);

        ajaxGet(url, function(data){
            btn.prop('disabled', false).removeClass('loading');
            if(data && data.data !== false){
                btn.addClass('btn-success');
                setTimeout(function(){ btn.addClass('hidden').removeClass('btn-success'); }, 800);
            }
        });
    });

    // Products list: tick = print A4 label with the new template, untick = legacy TCPDF template
    $("body").on('change', ".new-a4-template-checkbox", function(){
        var url = String($(this).data('url')).replace(/enabled=1/, 'enabled=' + (this.checked ? 1 : 0));
        ajaxGet(url);
    });

    // Product label preview/download: use whatever is currently typed in this row's
    // price/date fields, even if not saved yet (no request is sent to save them —
    // the values just ride along as query params for this one print/preview).
    $("body").on('click', "a.print-with-live-price", function (event) {
        var productsId = $(this).data('products-id');
        var row = $(this).closest('tr');
        var price = row.find("input[name='price__"+productsId+"']").val();
        var priceAction = row.find("input[name='price_action__"+productsId+"']").val();
        var actionFrom = row.find("input[name='action_from__"+productsId+"']").val();
        var actionTo = row.find("input[name='action_to__"+productsId+"']").val();

        var params = "&overridePrice=" + encodeURIComponent(price || '')
            + "&overridePriceAction=" + encodeURIComponent(priceAction || '')
            + "&overrideActionFrom=" + encodeURIComponent(actionFrom || '')
            + "&overrideActionTo=" + encodeURIComponent(actionTo || '');

        this.href = this.href + (this.href.indexOf('?') === -1 ? '?' : '&') + params.substring(1);
    });

    $("body").on('change', "input.url-source", function (event) {
        $($(this).data('target')).val(stringToUrl($(this).val()));
    });

    $("input.webalize").on('change', function () {
        $(this).val(stringToUrl($(this).val()));
    });

    $("body").on('mousedown', "form:not(.ajax) .btn-success", function (event) {
        if ($(".form-group.has-error").length) {
            $(".nav-tabs a[href=#" + $(".form-group.has-error:first").closest('.tab-pane').attr('id') + ']').trigger('click');
        }
    });

    $("body").on('change', ".change-to-ajax", function (event) {
        ajaxGet(String($(this).data('url')).replace('-1', $(this).val()));
    });

    $("body").on('change', ".change-to-call", function (event) {
        window.location.href = String($(this).data('url')).replace('-1', $(this).val());
    });

    $("body").on('change', ".change-to-submit", function (event) {
        $(this).closest("form").submit();
    });

    $("body").on('keydown', ".enter-to-change", function (event) {
        if(event.keyCode == 13)
        {
            event.preventDefault();
            $(this).trigger("change");
        }
    });

    $("body").on('keydown', ".enter-to-submit", function (event) {
        if(event.keyCode == 13)
        {
            event.preventDefault();
            $(this).closest("form").submit();
        }
    });

    $("body").on("shown.bs.dropdown", ".dropdown", function () {
        var $ul = $(this).children(".dropdown-menu");
        var $button = $(this).children(".dropdown-toggle");
        var ulOffset = $ul.offset();
        var spaceUp = (ulOffset.top - $button.height() - $ul.height()) - $(window).scrollTop();
        var spaceDown = $(window).scrollTop() + $(window).height() - (ulOffset.top + $ul.height());

        if (spaceDown < 0 && (spaceUp >= 0 || spaceUp > spaceDown)) {
            $(this).addClass("dropup");
        }
    }).on("hidden.bs.dropdown", ".dropdown", function () {
        $(this).removeClass("dropup");
    });

    $("body").on("click","a.clipboard-copy", function(){
        var clipboardData = [],
            $temp = $("<input>"),
            inputText = $(this).parents(".input-group").find("input").val();

        $("body").append($temp);

        if($(this).data('prefix')){
            clipboardData.push($(this).data('prefix'));
        }

        if(inputText){
            clipboardData.push(inputText);
        }

        if($(this).data('sufix')){
            clipboardData.push($(this).data('sufix'));
        }

        $temp.val(clipboardData.join("/")).select();

        document.execCommand("copy");
        $temp.remove();
    });

    init();
    initChangeHandles();
});


var ajaxFormFile = function (action, data, callback) {
    destroyAllWSW();
    destroyAllIconPickers();
    destroyAllColorPickers();
    $.ajax({
        url: action,
        data: data,
        type: 'POST',
        contentType: false,
        processData: false,
        success: function (payload) {
            destroyAllSelects();
            ajaxCallback(payload, callback);
        }
    });
}

var ajaxGet = function (href, callback) {
    destroyAllWSW();
    destroyAllIconPickers();
    destroyAllColorPickers();
    $.ajax({
        type: 'get',
        url: href,
        cache: false,
        success: function (payload) {
            destroyAllSelects();
            ajaxCallback(payload, callback);
        }
    });
}

var ajaxPost = function (action, data, callback) {
    destroyAllWSW();
    destroyAllIconPickers();
    destroyAllColorPickers();
    $.post(action, data, function (payload) {
        ajaxCallback(payload, callback);
    });
}

var tabsIds = [];

var ajaxCallback = function (payload, callback) {
    if (payload.snippets) {
        for (var i in payload.snippets) {
            $('#' + i).html(payload.snippets[i]);

            $('#' + i + ' form').each(function () {
                Nette.initForm(this);
            });
        }
    }

    //remove flashes so the wont show double
    if(typeof payload.snippets == "undefined" || typeof payload.snippets['snippet--flashMessages'] == "undefined"){
        $('#snippet--flashMessages').html('');
    }

    if (payload.remove) {
        $(payload.remove).remove();
    }

    if (payload.redirect) {
        location.href = payload.redirect;
    }

    if(payload.openItem){
        $(payload.openItem.list).each(function(){
            $(this).find('[data-id="'+payload.openItem.id+'"]').find(payload.openItem.editLink).trigger('click');
        })
    }

    if(payload.toggleClick){
        $(payload.toggleClick).trigger('click');
    }

    if(payload.activateTab){
        $('a[href="'+payload.activateTab+'"]').closest('ul').find('li.active').removeClass('active');
        $('a[href="'+payload.activateTab+'"]').closest('li').addClass('active');
        $(payload.activateTab).closest('.tab-content').find('div[role=tabpanel]').removeClass('active');
        $(payload.activateTab).addClass('active');
    }

    if (payload.fieldItemsData) {
        for (var i in payload.fieldItemsData) {
            if (payload.fieldItemsData[i]) {
                for (var k in payload.fieldItemsData[i]) {
                    for (var d in payload.fieldItemsData[i][k]) {
                        if ($("#frm-" + i + " option[value=" + k + "]").length) {
                            $("#frm-" + i + " option[value=" + k + "]").data(d, payload.fieldItemsData[i][k][d]);
                            $("#frm-" + i + " option[value=" + k + "]").attr('data-' + d, payload.fieldItemsData[i][k][d]);
                        }
                    }
                }
            }
        }
    }

    if (payload.fieldItems) {
        for (var f in payload.fieldItems) {
            if (payload.fieldItems[f]) {
                for (var i in payload.fieldItems[f]) {
                    for (var k in payload.fieldItems[f][i]) {
                        var option = $('<option>').attr('value', k).html(payload.fieldItems[f][i][k]);
                        if (payload.fieldItemsData && payload.fieldItemsData[f + "-" + i] && payload.fieldItemsData[f + "-" + i][k]) {
                            for (var d in payload.fieldItemsData[f + "-" + i][k]) {
                                option.data(d, payload.fieldItemsData[f + "-" + i][k][d]);
                                option.attr('data-' + d, payload.fieldItemsData[f + "-" + i][k][d]);
                            }
                        }
                        $("#frm-" + f + "-" + i).append(option);

                        if($("#frm-" + f + "-" + i).prop('multiple')){
                            var val = $("#frm-" + f + "-" + i).val()
                            Array.isArray(val) ? val.push(k) : val = [k]
                            $("#frm-" + f + "-" + i).val(val)
                        }
                        else{
                            $("#frm-" + f + "-" + i).val(k)
                        }
                        $("#frm-" + f + "-" + i).trigger('change');
                    }
                }
            }
        }
    }

    if (payload.forms) {
        for (var f in payload.forms) {
            if (payload.forms[f]) {
                for (var i in payload.forms[f]) {
                    if($("#frm-" + f + "-" + i).prop('multiple')){
                        var val = $("#frm-" + f + "-" + i).val()
                        Array.isArray(val) ? val.push(payload.forms[f][i]) : val = [payload.forms[f][i]]
                        $("#frm-" + f + "-" + i).val(val);
                    }
                    else{
                        $("#frm-" + f + "-" + i).val(payload.forms[f][i]);
                    }

                }
            }
        }
    }

    if (callback) {
        callback(payload);
    }

    init();
};

var gridBulkActions = {
    selectedIds: [],
    addSelectedId: function (selectedId) {
        if (this.selectedIds.indexOf(selectedId) < 0) {
            this.selectedIds.push(selectedId);
        }
    },
    removeSelectedId: function (selectedId) {
        var index = this.selectedIds.indexOf(selectedId);

        if (index >= 0) {
            this.selectedIds.splice(index, 1);
        }
    },
    pushSelectedIdsToInput: function(){
        $('.bulk-events-container').find('input[name="selected_ids"]').val(this.selectedIds.join(','));
    },
    init: function () {
        var _this = this;

        $('body').on('change', 'input.bulk-check-all', function () {
            var checked = this.checked;
            $('.bulk-checkbox').each(function(e){
                $(this).prop('checked', checked);
                if(checked){
                    _this.addSelectedId($(this).data('id'))
                }
                else{
                    _this.removeSelectedId($(this).data('id'))
                }
            })

            _this.pushSelectedIdsToInput()
        });

        $('body').on('change', 'input.bulk-checkbox', function () {
            var checked = this.checked;
            if(checked){
                _this.addSelectedId($(this).data('id'))
            }
            else{
                _this.removeSelectedId($(this).data('id'))
            }

            _this.pushSelectedIdsToInput()
        });
    }
};

var initGridBulkActions = function () {
    gridBulkActions.init();
};

var init = function () {
    initSelect2();
    initTooltips();
    initDatepickers();
    initIconpickers();
    initColorPickers();
    openPreviousTabs();
    initWSW();
    initMultiplicator();
    jsFiltersInit();
    initChangeCallbacks();
    initLightbox();
    initSortableBoxes();
    initToggles();
    initGridBulkActions();
    initDropzone();
    initImageCrop();
    initProductRating();
    initPacketa();
    showFlashMessages();
    initAutocompletes();
};


var openPreviousTabs = function() {
    $(document).on('click', '.panel-body [data-toggle="tab"]', function (e){
        createCookie('activeTab', $(this).attr('href'), 1)
    })
    var previousActiveTabUrl = readCookie('previousActiveTabUrl')
    if(previousActiveTabUrl == window.location.pathname){
        var previousActiveTab = readCookie('activeTab')
        if(previousActiveTab){
            var previousActiveParentTabElem = $('.panel-body a[href="'+previousActiveTab+'"]').closest('.tab-pane')
            if(previousActiveParentTabElem.length > 0){
                $('.panel-body a[href="#'+previousActiveParentTabElem.attr('id')+'"]').tab('show');
            }
            $('.panel-body a[href="'+previousActiveTab+'"]').tab('show');
        }
    }
    else{
        eraseCookie('activeTab')
    }
    createCookie('previousActiveTabUrl', window.location.pathname, 1)

    function createCookie(name, value, days) {
        var expires;

        if (days) {
            var date = new Date();
            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
            expires = "; expires=" + date.toGMTString();
        } else {
            expires = "";
        }
        document.cookie = encodeURIComponent(name) + "=" + encodeURIComponent(value) + expires + "; path=/";
    }

    function readCookie(name) {
        var nameEQ = encodeURIComponent(name) + "=";
        var ca = document.cookie.split(';');
        for (var i = 0; i < ca.length; i++) {
            var c = ca[i];
            while (c.charAt(0) === ' ')
                c = c.substring(1, c.length);
            if (c.indexOf(nameEQ) === 0)
                return decodeURIComponent(c.substring(nameEQ.length, c.length));
        }
        return null;
    }

    function eraseCookie(name) {
        createCookie(name, "", -1);
    }
}


var initToggles = function () {
    // toggle trigger
    $("body").on('keyup change', ".toggle-trigger", function () {
        var trigger = $(this);
        update(trigger);
    });

    $(".toggle-trigger").each(function (e) {
        var trigger = $(this);
        update(trigger);
    })

    function update(trigger) {
        if (trigger.data('target')) {
            $(trigger.data('target')).each(function () {
                if (trigger.is('input[type="radio"]') && !trigger.is(':checked')) {
                    return false;
                }

                var val = trigger.is('input[type="checkbox"]') ? (trigger.is(':checked') ? '1' : '0') : trigger.val();

                if($(this).data('show-on') == '*'){
                    if(val == null || val == ''){
                        $(this).hide();
                    }
                    else{
                        $(this).show();
                    }
                }
                else{
                    var showOn = String($(this).data('show-on')).split('|');

                    if (showOn.indexOf(val) !== -1) {
                        $(this).show();
                    }
                    else {
                        $(this).hide();
                    }
                }

            });
        }
    }
}
var saveTabs = function (id) {
    if ($("#" + id).find('.nav-tabs').length) {
        tabsIds[id] = [];
        $("#" + id).find('.nav-tabs').each(function (i) {
            var link = $(this).find('li.active a').attr('href');
            tabsIds[id][i] = link;
        })
    }
}

var setTabs = function (id) {

    if ($("#" + id).find('.nav-tabs').length) {
        $("#" + id).find('.nav-tabs').each(function (i) {

            var _this = $(this);

            if (tabsIds[id] != undefined) {

                var link = tabsIds[id][i];
                _this.find("li a[href='" + link + "']").trigger('click');
            }
        })
    }

}

var initSelect2 = function () {
    $("select:not(.with-search):not(.no-search):not(.select-default)").each(function(e){
        var options = {
            minimumResultsForSearch: 10,
            width: '100%'
        };
        var modal = $(this).closest('.modal');
        if (modal.length) {
            options.dropdownParent = modal;
        }

        var placeholder = $(this).attr('placeholder');
        if(placeholder){
            options['placeholder'] = placeholder;
        }

        $(this).select2(options);
    });

    $("select.no-search:not(.select-default)").each(function(e){
        var options = {
            minimumResultsForSearch: 99999,
            width: '100%'
        };
        var modal = $(this).closest('.modal');
        if (modal.length) {
            options.dropdownParent = modal;
        }

        var placeholder = $(this).attr('placeholder');
        if(placeholder){
            options['placeholder'] = placeholder;
        }

        $(this).select2(options);
    });

    $("select.with-search:not(.select-default)").each(function(e){
        var options = {
            width: '100%',
        };
        var modal = $(this).closest('.modal');
        if (modal.length) {
            options.dropdownParent = modal;
        }

        var placeholder = $(this).attr('placeholder');
        if(placeholder){
            options['placeholder'] = placeholder;
        }

        $(this).select2(options);
    });

    $("select.tokenizer:not(.select-default)").each(function(e){
        var options = {
            width: '100%',
            tags: true,
            tokenSeparators: [',', ' ', ';']
        };
        var modal = $(this).closest('.modal');
        if (modal.length) {
            options.dropdownParent = modal;
        }

        $(this).select2(options);
    });
}

var initTooltips = function () {
    $('.tooltip').remove();
    $('[data-toggle~="tooltip"]').tooltip({
        container: 'body',
        trigger : 'hover',
        html: true
    });
}

var initDatepickers = function () {

    var days = ["Neděle", "Pondělí", "Úterý", "Středa", "Čtvrtek", "Pátek", "Sobota", "Neděle"];
    var daysShort = ["Ned", "Pon", "Úte", "Stř", "Čtv", "Pát", "Sob", "Ned"];
    var daysMin = ["Ne", "Po", "Út", "St", "Čt", "Pá", "So", "Ne"];
    var months = ["Leden", "Únor", "Březen", "Duben", "Květen", "Červen", "Červenec", "Srpen", "Září", "Říjen", "Listopad", "Prosinec"];
    var monthsShort = ["Led", "Úno", "Bře", "Dub", "Kvě", "Čvn", "Čvc", "Srp", "Zář", "Říj", "Lis", "Pro"];

    $.fn.datepicker.dates['cs'] = {
        months: months,
        monthsShort: monthsShort,
        days: days,
        daysShort: daysShort,
        daysMin: daysMin,
    }

    $(".date-picker").attr('autocomplete', 'off');
    $(".date-picker").datepicker({
        language: "cs",
        autoclose: true,
        format: 'dd.mm.yyyy',
        weekStart: 1,
    });

    $(".month-picker").attr('autocomplete', 'off');
    $(".month-picker").datepicker({
        language: "cs",
        format: "mm/yyyy",
        viewMode: "months",
        minViewMode: "months",
    });

    $(".datetime-picker").attr('autocomplete', 'off');
    $(".datetime-picker").datetimepicker({
        language: 'cs',
        autoclose: true,
        format: 'dd.mm.yyyy hh:ii',
        weekStart: 1
    });
}

var initWSW = function () {
    $(".wsw").each(function (index, value) {
        var textarea = $(this);
        var id = textarea.attr('id');

        /*try {
            CKEDITOR.instances[id].destroy(true);
        } catch (e) { }*/

        try {
            CKEDITOR.replace(id, {
                'width': textarea.width(),
                'height': textarea.height()
            });
        } catch (e) { }

        $(window).on('resize', function () {
            var parent = textarea.closest(".form-group");
            if (CKEDITOR.instances[id]) {
                try {
                    CKEDITOR.instances[id].resize(parent.width(), textarea.height(), true);
                } catch (e) { }
            }
        });
    });
}

var destroyAllWSW = function () {
    for (name in CKEDITOR.instances) {
        try {
            CKEDITOR.instances[name].destroy();
        } catch (e) { }
    }
}

var destroyAllSelects = function () {
    $(".select2-dropdown").parent().remove();
}

var initChangeHandles = function () {
    $("body").on('change', "select.change-handler, input[type=text].change-handler, input[type=number].change-handler", function () {
        ajaxGet($(this).data('handler') + $(this).val());
    });
}

var stringToUrl = function (string) {
    var nodiac = {
        'á': 'a',
        'č': 'c',
        'ď': 'd',
        'é': 'e',
        'ě': 'e',
        'í': 'i',
        'ň': 'n',
        'ó': 'o',
        'ř': 'r',
        'š': 's',
        'ť': 't',
        'ú': 'u',
        'ů': 'u',
        'ý': 'y',
        'ž': 'z'
    };

    string = string.toLowerCase();

    var string2 = '';
    for (var i = 0; i < string.length; i++) {
        string2 += (typeof nodiac[string.charAt(i)] != 'undefined' ? nodiac[string.charAt(i)] : string.charAt(i));
    }

    return string2.replace(/[^a-z0-9_]+/g, '-').replace(/^-|-$/g, '');
}

var stringToUrl2 = function (string) {
    var nodiac = {
        'á': 'a',
        'č': 'c',
        'ď': 'd',
        'é': 'e',
        'ě': 'e',
        'í': 'i',
        'ň': 'n',
        'ó': 'o',
        'ř': 'r',
        'š': 's',
        'ť': 't',
        'ú': 'u',
        'ů': 'u',
        'ý': 'y',
        'ž': 'z'
    };

    string = string.toLowerCase();

    var string2 = '';
    for (var i = 0; i < string.length; i++) {
        string2 += (typeof nodiac[string.charAt(i)] != 'undefined' ? nodiac[string.charAt(i)] : string.charAt(i));
    }

    return string2.replace(/[^a-z0-9_\/]+/g, '-').replace(/^-|-$/g, '').replace(/^-\/-$/g, '/');
}

// tabs - default
$(function () {
    var hash = location.hash;

    if ($(hash).length && $(hash).hasClass('tab-pane')) {
        $(".nav-tabs li:eq(" + ($(hash).index()) + ") a").trigger('click');
    }
});

// checkboxes
$(function () {

    $("body").on('change', ".checkbox-table-group-trigger-head input", function () {
        var checked = this.checked;
        $(this).closest("table").find("tr input").each(function () {
            $(this)[0].checked = checked;
            $.uniform.update($(this));
        });
    });

    $("body").on('change', ".checkbox-table-group-trigger input", function () {
        var checked = this.checked;
        $(this).closest("tr").nextUntil('.checkbox-table-group-trigger').find("input").each(function () {
            $(this)[0].checked = checked;
            $.uniform.update($(this));
        });
    });

    $("body").on('change', ".checkbox-group-trigger", function () {
        var checked = this.checked;
        $(this).closest(".checkbox-group").find(".checkbox-group-items input").each(function () {
            $(this)[0].checked = checked;
            $.uniform.update($(this));
        });
    });

    $("body").on('change', ".readonly input[type=checkbox]", function () {
        $(this)[0].checked = !this.checked;
        $.uniform.update($(this));
    });
});

// dropzone

var initDropzone = function () {
    Dropzone.autoDiscover = false;

    $("div.dropzone:not(.dropzone-attached)").each(function (i) {
        var dropzone = $(this);

        dropzone.addClass('dropzone-attached');
        ;var directory = dropzone.data('directory');
        var url = "/system/dropzone/upload?directory=" + directory;

        $("#" + dropzone.attr('id')).dropzone({
            maxFiles: 20,
            url: url,
        });
    })

    $(".dropzone-component-remove-button").on('click', function (e) {
        e.preventDefault();
        var component = $(this).closest(".dropzone-component");
        $(this).closest(".item-list").remove();
        initTooltips();
        updateDropzoneList(component);
    });

    $(".dropzone-component .list").sortable({
        items: '.sortable_box',
        handle: ".ico_move",
        stop: function () {
            updateDropzoneList($(this).closest(".dropzone-component"));
        }
    });

    $(".dropzone-component .form-group input[type=text]").on('keyup', function () {
        var input = $(this);
        var data = input.closest('.item-list').data('dropzone');
        data.name = input.val();
        input.closest('.item-list').data('dropzone', data);

        updateDropzoneList(input.closest(".dropzone-component"));
    })

    $(".dropzone-component .item-list-param").on('change', function (e) {
        e.preventDefault();

        var input = $(this);
        var data = input.closest('.item-list').data('dropzone');
        var param = input.data('param');
        data[param] = input.val();
        input.closest('.item-list').data('dropzone', data);

        updateDropzoneList(input.closest(".dropzone-component"));
    });

    var updateDropzoneList = function (component) {
        var dropzoneItems = [];
        component.find('.item-list').each(function () {
            dropzoneItems.push(($(this).data('dropzone') !== null && typeof $(this).data('dropzone') === 'object') ? JSON.stringify($(this).data('dropzone')) : $(this).data('dropzone'));
        });
        component.find(".dropzone-component-list").html(dropzoneItems.join('; '));
    }
}

// boxes sorting
var initSortableBoxes = function () {
    $("div.sortable").sortable({
        items: '.sortable-item',
        handle: '.move-handler',
        axis: 'y',
        opacity: 0.7,
        start: function (event, ui) {
            for (name in CKEDITOR.instances) {
                if(ui.item.find('#'+name).length > 0){
                    try {
                        CKEDITOR.instances[name].destroy();
                    } catch (e) { }
                }
            }
        },
        stop: function (event, ui) {
            var itemId = ui.item.data('id');
            var customHandle = ui.item.data('custom-handle');
            var aboveId = ui.item.next(".sortable-item").length ? ui.item.next(".sortable-item").data('id') : 0;
            var belowId = ui.item.prev(".sortable-item").length ? ui.item.prev(".sortable-item").data('id') : 0;

            if (customHandle) {
                if (itemId && aboveId) {
                    ajaxGet('?itemId=' + itemId + '&aboveId=' + aboveId + '&do=move' + customHandle + 'Above');
                }
                else if (itemId && belowId) {
                    ajaxGet('?itemId=' + itemId + '&belowId=' + belowId + '&do=move' + customHandle + 'Below');
                }
            }
            else {
                if (itemId && aboveId) {
                    ajaxGet('?itemId=' + itemId + '&aboveId=' + aboveId + '&do=moveAbove');
                }
                else if (itemId && belowId) {
                    ajaxGet('?itemId=' + itemId + '&belowId=' + belowId + '&do=moveBelow');
                }
            }
            initWSW();

        }
    });

    $("tbody:has(.move-handler)").sortable({
        items: 'tr',
        handle: '.move-handler',
        axis: 'y',
        opacity: 0.7,
        start: function (event, ui) {
            ui.item.find("td").each(function () {
                var index = $(this).index();
                $(this).css('width', $(this).closest("table").find("thead tr th:eq(" + index + ")").outerWidth());
            });
        },
        stop: function (event, ui) {
            var itemId = ui.item.data('id');
            var customHandle = ui.item.data('custom-handle');
            var aboveId = ui.item.next("tr").length ? ui.item.next("tr").data('id') : 0;
            var belowId = ui.item.prev("tr").length ? ui.item.prev("tr").data('id') : 0;

            if (customHandle) {
                if (itemId && aboveId) {
                    ajaxGet('?itemId=' + itemId + '&aboveId=' + aboveId + '&do=move' + customHandle + 'Above');
                }
                else if (itemId && belowId) {
                    ajaxGet('?itemId=' + itemId + '&belowId=' + belowId + '&do=move' + customHandle + 'Below');
                }
            }
            else {
                if (itemId && aboveId) {
                    ajaxGet('?itemId=' + itemId + '&aboveId=' + aboveId + '&do=moveAbove');
                }
                else if (itemId && belowId) {
                    ajaxGet('?itemId=' + itemId + '&belowId=' + belowId + '&do=moveBelow');
                }
            }
        }
    });

    // content blocks
    $(".content-blocks-sections").sortable({
        axis: "y",
        handle: ".move-handler",
        opacity: 0.7,
        stop: function (event, ui) {
            var itemId = ui.item.data('id');
            var controlName = ui.item.data('control-name');
            var aboveId = ui.item.next(".content-blocks-section").length ? ui.item.next(".content-blocks-section").data('id') : 0;
            var belowId = ui.item.prev(".content-blocks-section").length ? ui.item.prev(".content-blocks-section").data('id') : 0;

            if (itemId && aboveId) {
                ajaxGet('?' + controlName + '-itemId=' + itemId + '&' + controlName + '-aboveId=' + aboveId + '&do=' + controlName + '-moveSectionAbove');
            }
            else if (itemId && belowId) {
                ajaxGet('?' + controlName + '-itemId=' + itemId + '&' + controlName + '-belowId=' + belowId + '&do=' + controlName + '-moveSectionBelow');
            }
        }
    });

    $(".content-blocks-blocks").sortable({
        axis: "y",
        handle: ".move-handler",
        connectWith: ".content-blocks-blocks",
        opacity: 0.7,
        stop: function (event, ui) {
            var itemId = ui.item.data('id');
            var controlName = ui.item.data('control-name');
            var aboveId = ui.item.next(".content-blocks-block").length ? ui.item.next(".content-blocks-block").data('id') : 0;
            var belowId = ui.item.prev(".content-blocks-block").length ? ui.item.prev(".content-blocks-block").data('id') : 0;
            var sectionId = ui.item.closest(".content-blocks-section").data('id');

            if (itemId && aboveId) {
                ajaxGet('?' + controlName + '-itemId=' + itemId + '&' + controlName + '-aboveId=' + aboveId + '&' + controlName + '-sectionId=' + sectionId + '&do=' + controlName + '-moveBlockAbove');
            }
            else if (itemId && belowId) {
                ajaxGet('?' + controlName + '-itemId=' + itemId + '&' + controlName + '-belowId=' + belowId + '&' + controlName + '-sectionId=' + sectionId + '&do=' + controlName + '-moveBlockBelow');
            }
            else if (itemId && sectionId) {
                ajaxGet('?' + controlName + '-itemId=' + itemId + '&' + controlName + '-sectionId=' + sectionId + '&do=' + controlName + '-moveBlockToSection');
            }
        }
    });

    $(".content-blocks-blocks-in-menu").sortable({
        axis: "y",
        handle: ".move-handler",
        connectWith: ".content-blocks-blocks-in-menu",
        opacity: 0.7,
        stop: function (event, ui) {
            var itemId = ui.item.data('id');
            var controlName = ui.item.data('control-name');
            var aboveId = ui.item.next(".content-blocks-block").length ? ui.item.next(".content-blocks-block").data('id') : 0;
            var belowId = ui.item.prev(".content-blocks-block").length ? ui.item.prev(".content-blocks-block").data('id') : 0;
            var sectionId = ui.item.closest(".content-blocks-section").data('id');

            if (itemId && aboveId) {
                ajaxGet('?' + controlName + '-itemId=' + itemId + '&' + controlName + '-aboveId=' + aboveId + '&' + controlName + '-sectionId=' + sectionId + '&' + controlName + '-column=pos_order_in_menu' + '&do=' + controlName + '-moveBlockAbove');
            }
            else if (itemId && belowId) {
                ajaxGet('?' + controlName + '-itemId=' + itemId + '&' + controlName + '-belowId=' + belowId + '&' + controlName + '-sectionId=' + sectionId + '&' + controlName + '-column=pos_order_in_menu' + '&do=' + controlName + '-moveBlockBelow');
            }
        }
    });
}

$(function () {
    initSortableBoxes();
});

// js filters
$(function () {
    jsFiltersInit();
});

var jsFiltersInit = function () {
    $(".js-filters").each(function () {
        jsFiltersInitFilter($(this));
    });
}

var jsFiltersInitFilter = function (container) {
    container.find("select.js-filters-field").each(function () {
        jsFiltersInitSelectboxes(container, $(this));
    });

    container.find(".js-filters-field").on('change keyup', function (event) {
        event.preventDefault();
        event.stopPropagation();
        jsFiltersApply(container);
    });

    jsFiltersApply(container);
}

var jsFiltersInitSelectboxes = function (container, selectbox) {
    var defaultValue = selectbox.val();
    selectbox.find("option").remove();
    selectbox.append($("<option>").attr('value', '').html('-'));
    var index = selectbox.closest("td").index();
    var values = [];
    container.find(".js-filters-item").each(function () {
        var value = $(this).find("td:eq(" + index + ")").text();
        if (values.indexOf(value) < 0) {
            values.push(value);
        }
    });
    if (!selectbox.hasClass('js-filters-field-no-sort')) {
        values.sort();
    }
    values.map(function (value) {
        selectbox.append($("<option>").attr('value', value).html(value));
    });
    selectbox.val(defaultValue);
}

var jsFiltersGetFilterFields = function (container) {
    var filterFields = [];
    container.find(".js-filters-field").each(function () {
        if ($(this).val()) {
            filterFields.push({
                index: $(this).closest("td").index(),
                value: $(this).val(),
                type: $(this)[0].tagName,
                regexp: new RegExp($(this).val(), "i")
            });
        }
    });
    return filterFields;
}

var jsFiltersApply = function (container) {
    var filterFields = jsFiltersGetFilterFields(container);
    container.find(".js-filters-item").each(function () {
        var row = $(this);
        var visible = true;
        filterFields.map(function (field) {
            if (row.data('type') == 'customRestriction') {
                var value = String(row.find('label').html());
            }
            else {
                var value = String(row.find("td:eq(" + field.index + ")").text());
            }

            if (field.type === 'SELECT' && field.value !== value) {
                visible = false;
            }
            else if (field.type === 'INPUT' && value.search(field.regexp) === -1) {
                visible = false;
            }
        });
        if (visible) {
            row.removeClass('hidden');
        }
        else {
            row.addClass('hidden');
        }
    });
}


// multiplicator
var initMultiplicator = function() {
    $("body").off('click', ".multiplicator-add").on('click', ".multiplicator-add", function(event) {
        event.preventDefault();
        var container = $(this).closest(".multiplicator");
        var pattern = container.find(".multiplicator-pattern").clone();
        var items = container.find(".multiplicator-items");
        items.append(pattern.removeClass('multiplicator-pattern').addClass('multiplicator-item'));
        items.find(".multiplicator-item:last .select2").remove();
        var wswItem =  items.find(".multiplicator-item:last .wsw-after-multiply")
        wswItem.addClass('wsw')
        wswItem.attr('id', wswItem.attr('id')+'-'+Math.random().toString(36).slice(2)+'-item');
        initSelect2();
        initTooltips();
        initWSW();
    });

    $("body").off('click', ".multiplicator-remove").on('click', ".multiplicator-remove", function(event) {
        event.preventDefault();
        $(this).closest(".multiplicator-item").remove();
        $('.tooltip.fade').remove();
    });

    $(".multiplicator").each(function() {
        var initCount = $(this).data('multiplicator-init-count') ? parseInt($(this).data('multiplicator-init-count')) : null;
        var count = $(this).find(".multiplicator-item").length;

        if(count < initCount) {
            for(i = count; i < initCount; i++) {
                $(this).find(".multiplicator-add").trigger('click');
            }
        }
    });
}


// change callbacks
var initChangeCallbacks = function () {
    $("body").off('change', ".change-callback").on('change', ".change-callback", function (event) {
        var url = $(this).data('change-callback-url');
        if (url) {
            ajaxGet(url + $(this).val());
        }
    });
}

// checkboxes
$(function () {
    $(".checkboxes-multi-trigger").on('click', function (event) {
        event.preventDefault();
        var targetClass = $(this).data('target-class');
        if ($("input." + targetClass + ":not(:checked)").length > 0) {
            $("input." + targetClass).prop('checked', true).uniform('update');
        }
        else {
            $("input." + targetClass).prop('checked', false).uniform('update');
        }
    });
});


var initLightbox = function () {
    $('.photo-popup').magnificPopup({
        type: 'image',
        gallery: {
            enabled: true,
            navigateByImgClick: true,
            preload: [0, 1]
        }
    });
}


//icon pickers

$(function(e){
    $(document).on('change', '.icon-picker', function(e){
        if($(this).val()){
            $(this).closest('.icon-picker-input-group').find('.input-group-addon').find('i').attr('class', $(this).val())
        }
        else{
            $(this).closest('.icon-picker-input-group').find('.input-group-addon').find('i').attr('class', 'fa fa-question')
        }

    })
})

var initColorPickers = function () {
    $(".color-picker").each(function () {

        var input = $(this);
        input.colorpicker({
            format: 'hex'
        }).on('changeColor', function(event) {
            var addon = $(this).parent().find('.input-group-addon');
            if(addon.length > 0){
                addon.css({'background-color': event.color.toHex()});
            }
        });
        input.on('change keyup', function(event){
            var addon = input.parent().find('.input-group-addon');
            if(addon.length > 0){
                addon.css({'background-color': input.val()});
            }
        })
        input.attr('autocomplete', 'off');

        var group = input.closest(".form-group");

        var inputGroup = $("<div class=\"input-group color-picker-input-group\"></div>");

        inputGroup.append(input);

        $('<span class=\"input-group-addon\" style="min-width: 34px;">&nbsp;</span>').insertBefore(input);

        inputGroup.appendTo(group);


        if(input.val()){
            group.find('.color-picker-input-group').find('.input-group-addon').css({'background-color': input.val()});
        }
    });
}
var destroyAllColorPickers = function () {
    $('.color-picker-input-group').each(function(e){
        $(this).find('.input-group-addon').remove();
        var input = $(this).html()
        $(this).after(input);
        $(this).next().removeAttr('style')
        $(this).remove();
    })
}

var initIconpickers = function () {
    if ($(".icon-picker").length) {
        $('.icon-picker').each(function(){
            var input = $(this);

            input.iconpicker({
                defaultValue: input.val(),
                hideOnSelect: true,
                templates: {
                    search: '<input type="search" class="form-control iconpicker-search" placeholder="Vyhledat" />',
                },
                placement: 'bottomLeft'
            }).on('iconpickerUpdated', function(event){
                input.closest('.icon-picker-input-group').find('.input-group-addon').find('i').attr('class', event.iconpickerValue);
            });
            input.attr('autocomplete', 'off');


            var group = input.closest(".form-group");

            var inputGroup = $("<div class=\"input-group icon-picker-input-group\"></div>");

            inputGroup.append(input);

            $('<span class=\"input-group-addon\"><i class=\"fa fa-question\"></i></span>').insertBefore(input);

            inputGroup.appendTo(group);


            if(input.val()){
                group.find('.icon-picker-input-group').find('.input-group-addon').find('i').attr('class', input.val());
            }

        })
    }

}

var destroyAllIconPickers = function () {
    $('.icon-picker-input-group').each(function(e){
        $(this).find('.input-group-addon').remove();
        var input = $(this).html()
        $(this).after(input);
        $(this).remove();
    })
}


var showFlashMessages = function () {
    $("div.flash-messages").each(function (i) {
        var text = $(this).html();
        if ($(this).hasClass("info") || $(this).hasClass("success")) {
            alertify.success(text);
        }
        if ($(this).hasClass("error") || $(this).hasClass("danger")) {
            alertify.error(text);
        }
        if ($(this).hasClass("alert")) {
            alertify.alert(text);
        }
    })
}

var initProductRating = function () {
    if($('.rating-input').length > 0){
        $('.rating-input').each(function(e){
            $(this).find('a').on('mouseenter', function(e){
                var rating = $(this).data('rating');
                $(this).closest('.rating-input').find('a').each(function(e){
                    if($(this).data('rating') <= rating){
                        $(this).addClass('hover').removeClass('no-hover');
                    }
                    else{
                        $(this).removeClass('hover').addClass('no-hover');
                    }
                })
            })
            $(this).on('mouseleave', function(e){
                $(this).find('a').removeClass('hover').removeClass('no-hover');
            })
            $(this).find('a').on('click', function(e){
                e.preventDefault();
                var rating = $(this).data('rating');
                $(this).closest('.rating-input').find('a').each(function(e){
                    if($(this).data('rating') <= rating){
                        $(this).addClass('selected');
                    }
                    else{
                        $(this).removeClass('selected');
                    }
                })
                $(this).closest('.rating-input').find('input').val(rating)
            })
        })
    }
}




/**
 * IMG CROP
 */
var initImageCrop = function() {
    var image = $('img.image_crop');
    if (image.length > 0) {

        var base = $('a.crop-send').attr('href');
        image.cropper({
            viewMode: 1,
            zoomable: false,
            autoCrop: 1,
            data: (image.data('init-width')) ? {
                width: image.data('init-width'),
                height:  image.data('init-height'),
                x: image.data('init-x'),
                y: image.data('init-y'),
            } : null,
            crop: function (event) {
                var url = base;
                url = url.replace('x=0', 'x=' + Math.round(event.detail.x));
                url = url.replace('y=0', 'y=' + Math.round(event.detail.y));
                url = url.replace('width=0', 'width=' + Math.round(event.detail.width));
                url = url.replace('height=0', 'height=' + Math.round(event.detail.height));
                $('a.crop-send').attr('href', url);
            }
        });
    }

}
$(document).on('click', 'a.crop-send, a.crop-remove', function(event){
    event.preventDefault();

    ajaxGet(this.href, function(data) {
        $('img').each(function(){
            $(this).attr('src', $(this).attr('src')+'?'+new Date().getTime());
        });
    });
})


//slide toggle
$(function(){

    var slideToggle = function(handler, target){
        $(target).slideToggle(200, function(){
            $(target).find('.wsw').each(function(e){
                var textarea = $(this);
                var id = $(this).attr('id');
                var parent = textarea.closest(".form-group");

                if (CKEDITOR.instances[id]) {
                    try {
                        CKEDITOR.instances[id].resize(parent.width(), textarea.height(), true);
                    } catch (e) { }
                }
            })
        });

        var icon = handler.find('i');
        if(icon.length > 0){
            if(icon.hasClass('fa-chevron-down')){
                icon.removeClass('fa-chevron-down');
                icon.addClass('fa-chevron-up');
            }
            else{
                if(icon.hasClass('fa-chevron-up')){
                    icon.removeClass('fa-chevron-up');
                    icon.addClass('fa-chevron-down');
                }
            }
        }
    }

    $("body").on('click', ".slide-toggle", function (event) {
        event.preventDefault();
        var handler = $(this);
        handler.blur();
        var target = handler.data('target');
        slideToggle(handler, target);

    });

    $('.slide-toggle').each(function(e){
        var handler = $(this);
        var target = handler.data('target');
        if(handler.attr('data-target') == '.filters-container' || handler.attr('data-target') == '.filters-view-container'){
            $(target).find('input, select, textarea').each(function(e){
                if($(this).val()){
                    slideToggle(handler, target);
                    return false;
                }
            })
        }
    })


})

//page congtent blocks show/hide
$(function(){
    if($('#frm-editForm-page_types_id').length > 0){
        var showOn = String($('#frm-editForm-page_types_id').data('show-content-blocks-on')).split('|');
        displayChanges();

        $(document).on('change', '#frm-editForm-page_types_id', function(e){
            displayChanges();
        })

        function displayChanges(){
            var id = $('#frm-editForm-page_types_id').val();
            if (showOn.indexOf(id) !== -1) {
                $('.page-content-blocks-wrapper').show();
                $('.page-contents-wrapper').hide();
            }
            else {
                $('.page-content-blocks-wrapper').hide();
                $('.page-contents-wrapper').show();
            }
        }
    }
})



// image controls
$(function() {
    $("body").on('click', ".ico_del", function(e) {
        e.preventDefault();

        var href = $(this).attr('href');
        var container = $(this).closest(".container_del_box");

        $.ajax({
            type: 'get',
            url: href,
            cache: false,
            success: function (payload) {
                container.remove();
                initTooltips();
            }
        });
    })
    $("body").on('click', ".ico_rotate", function(e) {
        e.preventDefault();
        var img = $(this).closest(".container_rotate_box").find('img');

        $.ajax({
            type: 'get',
            url: $(this).attr('href'),
            cache: false,
            success: function (payload) {
                var src = String(img.attr('src'));
                var trueSrc = src.split('?');
                img.attr('src', trueSrc[0]+'?'+$.now());
            }
        });
    })

    $("body").on('change', ".image-title", function(e) {
        e.preventDefault();

        var input = $(this);
        var href = input.data('href');
        href = href.replace('replace_title', input.val());

        $.ajax({
            type: 'get',
            url: href,
            cache: false,
            success: function (payload) {
                input.closest('.input-group').addClass('has-success');
            }
        });
    })

    $("body").on('keyup click', ".image-title", function(e) {
        e.preventDefault();
        var input = $(this);
        input.closest('.input-group').removeClass('has-success');
    })

    $("body").on('change', ".file-title", function(e) {
        e.preventDefault();

        var input = $(this);
        var href = input.data('href');
        href = href.replace('replace_title', input.val());

        $.ajax({
            type: 'get',
            url: href,
            cache: false,
            success: function (payload) {
                input.closest('.input-group').addClass('has-success');
            }
        });
    })

    $("body").on('keyup click', ".file-title", function(e) {
        e.preventDefault();
        var input = $(this);
        input.closest('.input-group').removeClass('has-success');
    })
})


//copy content blocks
$(function(e){
    $(document).on('change', '.copy-block-form select', function(e){
        var form = $(this).closest('.copy-block-form');
        var link = form.attr('href');

        var itemId = form.find('select[name="items_id"]').val();
        if($(this).attr('name') == 'table_name'){
            itemId = '';
        }

        link = link.replace('replace_sectionsId', form.find('input[name="sections_id"]').val());
        link = link.replace('replace_languagesId', form.find('select[name="languages_id"]').val());
        link = link.replace('replace_tableName', form.find('select[name="table_name"]').val());
        link = link.replace('replace_itemId', itemId);
        ajaxGet(link);
    })

    $(document).on('change', '.section-check-all', function(e){
        var checked = this.checked;
        $(this).closest('.content-blocks-section').find('input[type="checkbox"]').each(function(e){
            $(this).prop('checked', checked);
        })
    })
})



//configurators
$(function(e){
    $(document).on('click', '.configurator .configurator-item', function(e){
        var checkbox = $(this).find('input[type="checkbox"]');
        var configurator = $(this).closest('.configurator');
        if(checkbox.prop('checked')){
            checkbox.prop('checked', false);
        }
        else{
            var isMultiple = configurator.hasClass('multiple');
            if(!isMultiple){
                configurator.find('input[type="checkbox"]').prop('checked', false);
                configurator.find('.quantity').val('');
            }
            checkbox.prop('checked', true);
        }

    })
})


/**
 * PACKETA
 */
var initPacketa = function(){
    var widget = $('#packeta-widget');
    if(widget.length > 0){
        var button = widget.find('.select-branch');
        button.click(function(e){
            e.preventDefault();
            Packeta.Widget.pick(widget.data('api-password'), function(data){
                ajaxPost(button.attr('href'), data);
            }, {
                country: widget.data('country'),
                language: widget.data('language')
            })
        })
    }
}


// calendar btn
$(function() {
    $("body").on('click', ".calendar-today-overlay .calendar-today-btn", function(e) {
        e.preventDefault();

        var _this = $(this);
        _this.closest(".calendar-today-overlay").find(".date-picker").datepicker("update", new Date());
    })
})



// multi sale
var multiSalePriceTimer = null;
var multiSaleActionPriceTimer = null;
$(function() {
    $("body").on('change', ".multi-sale-price", function(e) {
        var _this = $(this);

        if (multiSalePriceTimer) {
            clearTimeout(multiSalePriceTimer);
        }

        multiSalePriceTimer = setTimeout(function() {
            ajaxGet('?do=setMultiSalePrice&productsId='+_this.data('id')+'&value='+_this.val());
        }, 500);
    })

    $("body").on('change', ".multi-sale-action-price", function(e) {
        var _this = $(this);

        if (multiSaleActionPriceTimer) {
            clearTimeout(multiSaleActionPriceTimer);
        }

        multiSaleActionPriceTimer = setTimeout(function() {
            ajaxGet('?do=setMultiSaleActionPrice&productsId='+_this.data('id')+'&value='+_this.val());
        }, 500);
    })
})



var initAutocompletes = function () {
    $(".autocomplete").each(function () {
        var autocomplete = $(this);
        var url = String(autocomplete.data('url')).replace('-1', $(this).val());

        autocomplete.autocomplete({
            source: url,
            minLength: 2,
            select: function (event, ui) {
                autocomplete.val(ui.item.value).trigger('change');
            }
        });
    });
}


$(function() {
    $("body").on('click', ".delete-barcodes", function(e) {
        e.preventDefault();

        var _this = $(this);

        alertify.confirm(_this.data('message'), function (e) {
            if (e) {
                var ids = [];

                _this.closest("table").find(".bulk-checkbox").each(function() {
                    if ($(this).is(":checked")) {
                        ids.push($(this).closest("tr").data('id'));
                    }
                })

                ajaxPost(_this.attr('href'), {ids: ids});
            }
        });
    })
})


$(function() {
    $("body").on('change', ".descriptions-type", function() {
        $(".description-tabs a[href='#tab2"+$(this).val()+"']").click();

        ajaxGet(String($(this).data('url')).replace('-1', $(this).val()));
    })

    if ($(".description-tabs").length && $(".descriptions-type").length) {
        $(".description-tabs a[href='#tab2"+$(".descriptions-type").val()+"']").click();

        ajaxGet(String($(".descriptions-type").data('url')).replace('-1', $(".descriptions-type").val()));
    }

    $('.description-tabs a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
        $(".descriptions-type[value='"+$(this).data('id')+"']").prop('checked', true).trigger('change');
    });
})