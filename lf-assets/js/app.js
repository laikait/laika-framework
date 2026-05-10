///////////////////
//// Functions ////
///////////////////
// Get Named Uri
function named(array = [])
{
    return array.length ? '/' + array.join('/') : '';
}

// Require Prorata Date
function requireProrataDate(element){
    let prorata_billing_date = $('#prorata_billing_date');
    prorata_billing_date.prop('required', $(element).is(':checked'));
}

// Set New Product Price in Form
function productTypeChange(element){
    let types = ['free', 'onetime', 'recurring'];
    let type = $('#' + element.id).val().toLowerCase();
    let paymentOneTime = $('#onetime_type');
    let paymentRecurring = $('#recurring_type');

    if (types.includes(type)) {
        if(type === 'free') {
            paymentOneTime.removeClass('app-d-block').addClass('app-d-none');
            paymentRecurring.removeClass('app-d-block').addClass('app-d-none');
        }else if(type === 'onetime'){
            paymentOneTime.removeClass('app-d-none').addClass('app-d-block');
            paymentRecurring.removeClass('app-d-block').addClass('app-d-none');
        }else if(type === 'recurring'){
            paymentOneTime.removeClass('app-d-block').addClass('app-d-none');
            paymentRecurring.removeClass('app-d-none').addClass('app-d-block');
        }
    }
}

// Display or Hide
function toggle(id){
    $('#'+id).toggleClass('app-d-none');
}

// New Product Invoice Type Select
function newProductInvoiceType(element) // Not Used Yet
{
    let forProduct = $('#forProduct');
    let forNonProduct = $('#forNonProduct');
    let showProducts = $('#showProducts');

    if ($(element).is(forProduct)) {
        forProduct.prop('checked', true);
        forNonProduct.prop('checked', false);
        showProducts.addClass('app-d-block').removeClass('app-d-none');
    } else if ($(element).is(forNonProduct)) {
        forProduct.prop('checked', false);
        forNonProduct.prop('checked', true);
        showProducts.addClass('app-d-none').removeClass('app-d-block');
    }
}

///////////////////////
//// API Functions ////
///////////////////////

async function api(url, postType, body = {}) {
    try {
        postType = postType.toUpperCase();
        const options = {
            method: postType,
            headers: {
                "Content-Type": "application/json",
                "Authorization": "Bearer " + token
            },
            redirect: "follow"
        };

        if (postType !== 'GET') {
            options.body = JSON.stringify(body);
        }

        const response = await fetch(url, options);
        const result = await response.json();
        return result;

    } catch (error) {
        console.error("API error:", error);
        return null;
    }
}
