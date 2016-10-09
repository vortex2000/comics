/*  Combines two JS objects. The attributes of the second take priority over
 *  the first's.
 */
function merge_options(obj1, obj2) {
    var obj3 = {};
    for (var attrname in obj1) {
        obj3[attrname] = obj1[attrname];
    }
    for (var attrname in obj2) {
        obj3[attrname] = obj2[attrname];
    }
    return obj3;
}

function destroy_popup() {
    $(this).dialog('destroy');
    blockreload = false;
}

function popup(title, html, buttons, options) {
    if (buttons === false) {
        buttons = {'Cancel': false};
    } else {
        buttons = buttons ? buttons : {};
    }

    options = options ? options : {};

    if (options.centered) {
        html = '<div align="center">' + html + '</div>';
    }

    //TODO: find out if there's an error-text class
    html += '<div class="error" id="popup-error-div" style="color:red"></div>';

    default_options = {
        bgiframe: true,
        resizable: false,
        width: 550,
        height: 375,
        modal: true,
        overlay: {backgroundColor: '#000', opacity: 0.5},
        close: function (ev, ui) {
            $(this).dialog('destroy');
            blockreload = false;
        }
    };

    if ((typeof buttons['Cancel'] == "undefined")) {
        if (Object.keys(buttons).length > 0 || Object.keys(buttons).length == 1 && buttons["OK"]) {
            if (buttons['Close'] == "undefined") {
                buttons['Cancel'] = destroy_popup;
            }
        } else if (typeof buttons['OK'] == "undefined") {
            buttons['OK'] = destroy_popup;
        }
    }

    if (!buttons['OK']) {
        delete buttons['OK'];
    }

    if (!buttons['Cancel']) {
        delete buttons['Cancel'];
    }

    options = merge_options(default_options, options);
    options.buttons = buttons;

    $('#fm_mp_text').html(html);
    $('#fm_mp_dialog').attr('title', title);
    $('#fm_mp_dialog').dialog(options);

    return function () {
        $('#fm_mp_dialog').dialog('destroy');
        blockreload = false;
    };
}

function nl2br(str, is_xhtml) {
    var breakTag = (is_xhtml || typeof is_xhtml === 'undefined') ? '<br />' : '<br>';
    return (str + '').replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, '$1' + breakTag + '$2');
}

function autobr(text) {
    if (text.match(/<[^>]+>/)) {
        return text;
    }
    return nl2br(text);
}

function set_popup_error(html) {
    $("#popup-error-div").html(html);
}
