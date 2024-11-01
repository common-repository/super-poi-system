(function ($) {
    $('.settings-error').each(function(){
        var inputId = $(this).attr('id').replace('setting-error-', '');
        console.log(inputId);
        $('#' + inputId).addClass('sps-error');
    });
})(jQuery);