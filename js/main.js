
jQuery(document).ready(function () {
    /*
     *  Simple image gallery. Uses default settings
     */

    jQuery('.fancybox').fancybox({
        width: "100%",
        height: "70%",
        autoSize: false,
        helpers: {
            padding: 0,
            overlay: {
                locked: false
            }
        }

    });



});