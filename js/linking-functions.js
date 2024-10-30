/**
 * Updates the linking table html (linking buttons and options)
 * @param showContainer
 */
function updateLinkedState(showContainer = true) {
    console.log("Updating links");
    var buttons = jQuery(".link-button");

    if(selectedItemID == 0) {
        jQuery("#kotobee-book-selection").hide();
        makeBtnUnLinked(buttons);
    } else {
        makeBtnUnLinked(buttons);

        //Reset options fields
        jQuery(".book-more-options input").val('');

        jQuery.each(buttons, function(index, button){
            let tr = jQuery(button).parent().parent()[0];
            updateItemRowHtml(tr);
        });
        if(showContainer)
            jQuery("#kotobee-book-selection").show();
    }
}

/**
 * Updates the html of one row to match the current linking state
 * @param tr
 */
function updateItemRowHtml(tr) {
    if(linkedObj.hasOwnProperty(selectedItemID)) {
        let kID = jQuery(tr).attr('data-id');
        let kType = jQuery(tr).attr('data-type');
        let button = jQuery(tr).find(".link-button");
        let chaptersField = jQuery(tr).find(".book-chapters");
        if(isItemLinked(selectedItemID, kType, kID)){
            makeBtnLinked(button);
            if(chaptersField.length > 0) {
                let options = linkedObj[selectedItemID][kType][kID];
                if(options.hasOwnProperty('chapters'))
                    chaptersField.val(options.chapters);
            }
        }
    }
}

/**
 * Checks whether a Wordpress item is linked with this kotobee item (using the linking object)
 *
 * @param itemID
 * @param kType
 * @param kID
 * @returns {boolean}
 */
function isItemLinked(itemID, kType, kID) {
    if(linkedObj[itemID].hasOwnProperty(kType))
        return linkedObj[itemID][kType].hasOwnProperty(kID);
    return false;
}

/**
 * When changing the library from the dropdown, this function hides the previous one and shows the selected.
 */
function showLibraryContainer() {
    var selectedLib = jQuery('#librarySelect option').filter(':selected').attr('value');
    jQuery('.libraryContainer').hide(500, function() {
        jQuery(".libraryContainer[data-id='" + selectedLib + "']").show();

    });
}

/**
 * Tab navigation
 * @param tabID
 */
function showTabContent(tabID) {
    jQuery(".kotobee-tab").removeClass('nav-tab-active');
    jQuery(".kotobee-tab#" + tabID).addClass('nav-tab-active');

    jQuery(".kotobee-tab-content").hide().removeClass('active');
    jQuery(".kotobee-tab-content[data-tab='" + tabID + "']").show().addClass('active');
}

/**
 * Makes an Ajax call to store/update a link
 * @param clicked
 */
function linkItem(clicked) {
    var tr = jQuery(clicked).closest('tr')[0];
    // var lib = jQuery(clicked).parents('.libraryContainer')[0];
    // var libID = jQuery(lib).attr('data-id');
    let kType = jQuery(tr).attr('data-type');
    let kID = jQuery(tr).attr('data-id');
    let options = null;
    let chaptersInput = jQuery(tr).find('.book-chapters');
    if(chaptersInput.length > 0)
        options = {"chapters": chaptersInput[0].value};

    showSpinner(tr);
    var args = {
        "action"    : linkingConfig.ajax_link_item,
        "nonce"     : linkingConfig.add_link_nonce,
        "itemID"    : selectedItemID,
        "itemType"    : currentItemType,
        // "libID"     : libID?libID:0,
        "kotobeeItemID"    : kID,
        "kotobeeType"  : kType,
        "options" : options
    };
    jQuery.post(ajaxurl, args,  function(response) {
        hideSpinner(tr);
        if(response.success == true) {
            if(jQuery(clicked).hasClass("link-button")) //if triggered by the link-button
                makeBtnLinked(clicked);
            updateLinkedObject(selectedItemID, kType, kID, "link");
        }
        else {
            alert(response.data);
        }

    }, 'json');
}

/**
 * Makes an Ajax call to remove an item linked before.
 * @param clicked
 */
function unlinkItem(clicked) {
    var tr = jQuery(clicked).parent().parent()[0];
    // var lib = jQuery(clicked).parents('.libraryContainer')[0];
    // var libID = jQuery(lib).attr('data-id');
    let kType = jQuery(tr).attr('data-type');
    let kID = jQuery(tr).attr('data-id');
    showSpinner(tr);
    var args = {
        "action"    : linkingConfig.ajax_unlink_item,
        "nonce"     : linkingConfig.remove_link_nonce,
        "itemID"    : selectedItemID,
        "itemType"    : currentItemType,
        // "libID"     : libID?libID:0,
        "kotobeeItemID"    : kID,
        "kotobeeType"  : kType
    };
    jQuery.post(ajaxurl, args,  function(response) {
        hideSpinner(tr);
        if(response.success == true) {
            makeBtnUnLinked(clicked);
            updateLinkedObject(selectedItemID, kType, kID, "unlink");
        }
        else {
            alert(response.data);
        }

    }, 'json');
}
function showSpinner(tr) {
    jQuery(tr).find('.spinner').addClass('is-active');
}
function hideSpinner(tr) {
    jQuery(tr).find('.spinner').removeClass('is-active');
}
function makeBtnLinked(button) {
    //Change button style and text
    jQuery(button).removeClass('button-primary').addClass('button-default').text(linkingConfig.translation.unlink).attr('onclick', 'unlinkItem(this)');
    //Show options
    let tr = jQuery(button).closest("tr");
    let options = tr.find('.more-options-button');
    if(options.length > 0) {
        tr.find('.book-more-options').hide();
        jQuery(options).show().attr("data-status", "closed");
    }
}
function makeBtnUnLinked(button) {
    //Change button style and text
    jQuery(button).removeClass('button-default').addClass('button-primary').text(linkingConfig.translation.link).attr('onclick', 'linkItem(this)');
    //Hide options
    let tr = jQuery(button).closest("tr");
    let options = tr.find('.more-options-button');
    if(options.length > 0) {
        jQuery(options).hide().attr("data-status", "closed");
        tr.find('.book-more-options').hide(300);
    }
}

/**
 * Updates the object: linkedObj that stores the linking state between kotobee and wordpress items
 * It should be triggered in the call back of every linking/unlinking ajax call to save the new state
 *
 * @param itemID
 * @param kotobeeType
 * @param kotobeeID
 * @param action
 * @param options
 */
function updateLinkedObject(itemID, kotobeeType, kotobeeID, action = "link", options = "") {
    // console.log("Updating linked object " + itemID + kotobeeType + kotobeeID + " " + action);
    if(action == "link") {
        let newObj = {};
        newObj[kotobeeID] = options;
        if(linkedObj.hasOwnProperty(itemID)) {
            if(linkedObj[itemID].hasOwnProperty(kotobeeType)){
                linkedObj[itemID][kotobeeType][kotobeeID] = options;
            } else {
                linkedObj[itemID][kotobeeType] = newObj;
            }
        } else {
            linkedObj[itemID] = {};
            linkedObj[itemID][kotobeeType] = newObj;
        }
    }
    if(action == "unlink") {
        delete linkedObj[itemID][kotobeeType][kotobeeID];

        // let index = linkedObj[itemID][kotobeeType].indexOf(kotobeeID);
        // linkedObj[itemID][kotobeeType].splice(index, 1);
    }
}

/**
 * Toggles book options form
 * @param clicked
 */
function toggleMoreOptions(clicked) {
    var tr = jQuery(clicked).closest("tr");
    let state = jQuery(clicked).attr("data-status");
    if(state == "closed") {
        jQuery(clicked).attr("data-status", "open");
        tr.find('.book-more-options').show(300);
    } else {
        jQuery(clicked).attr("data-status", "closed");
        tr.find('.book-more-options').hide(300);
    }
    // if(optionsDiv.length > 0)
    //     optionsDiv[0].show();
}
// ---- WooCommerce only script -----
/**
 * Load subitems of the selected item in #sub-items select
 */
function updateSubItems() {
    if(currentItemType == 'wooProduct') {
        var linkingBox = jQuery('#linking-box');
        jQuery("#sub-items").hide();
        showSpinner(linkingBox);
        var args = {
            "action"    : 'kotobee_get_product_variations',
            // "nonce"     : linkingConfig.remove_link_nonce,
            "product"    : selectedItemID
        };
        jQuery.post(ajaxurl, args,  function(response) {
            hideSpinner(linkingBox);
            console.log(response);
            if(response.success == true) {
                if(response.data.length > 0) {
                    let subItems = jQuery("#sub-items")
                    .empty()
                    .append("<option value='" + selectedItemID + "'>" + linkingConfig.translation.anyVariation + "</option>");
                    for(let i = 0; i < response.data.length; i++) {
                        let variation = response.data[i];
                        subItems.append("<option value='" + variation.id + "'>" + variation.label + "</option>");
                    }
                    jQuery("#sub-items").show();
                }
            }
            else {
                //alert(response.data);
            } 

        }, 'json');
    }

    
}