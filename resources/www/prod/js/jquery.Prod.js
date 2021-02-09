(function () {
    $(document).ready(function () {
	humane.info = humane.spawn({addnCls: 'humane-libnotify-info', timeout: 1000});
	humane.error = humane.spawn({addnCls: 'humane-libnotify-error', timeout: 1000});

	$('body').on('click', 'a.dialog', function (event) {
	    $('.context-menu').hide();
	    var $this = $(this), size = 'Medium';

	    if ($this.hasClass('small-dialog')) {
		size = 'Small';
	    } else if ($this.hasClass('full-dialog')) {
		size = 'Full';
	    }

	    var options = {
		size: size,
		loading: true,
		title: $this.attr('title'),
		closeOnEscape: true
	    };

	    $dialog = p4.Dialog.Create(options);

	    $.ajax({
		type: "GET",
		url: $this.attr('href'),
		dataType: 'html',
		success: function (data) {
		    $dialog.setContent(data);
		    return;
		}
	    });
//	    if ($this.hasClass('refresh-on-close-upload')) {
//		$("#DIALOG1").dialog({
//		    close: function (event, ui) {
//			var c_page = 1;
//
//			gotopage(c_page);
//		    }
//		});
//
//	    }

	    return false;
	});

    });

}());
