$(function()
{
	var timeline_block = $('.cd-timeline-block');
	var timeline_block_full = $('.cd-timeline-block-full');
	var bullet = $('.bullet');

	timeline_block.each(function()
	{
		if($(this).offset().top > $(window).scrollTop() + $(window).height() * 0.75)
		{
			$(this).find('.cd-timeline-img, .cd-timeline-content').addClass('is-hidden');
		}
	});

	timeline_block_full.each(function()
	{
		if($(this).offset().top > $(window).scrollTop() + $(window).height() * 0.75)
		{
			$(this).addClass('is-hidden');
		}
	});

	bullet.each(function()
	{
		if($(this).offset().top > $(window).scrollTop() + $(window).height() * 0.75)
		{
			$(this).addClass('is-hidden');
		}
	});

	$(window).on('scroll', function()
	{
		timeline_block.each(function()
		{
			if($(this).offset().top <= $(window).scrollTop() + $(window).height() * 0.75 && $(this).find('.cd-timeline-img').hasClass('is-hidden'))
			{
				$(this).find('.cd-timeline-img, .cd-timeline-content').removeClass('is-hidden').addClass('bounce-in');
			}
		});

		timeline_block_full.each(function()
		{
			if($(this).offset().top <= $(window).scrollTop() + $(window).height() * 0.75 && $(this).hasClass('is-hidden'))
			{
				$(this).removeClass('is-hidden').addClass('bounce-in');
			}
		});

		bullet.each(function()
		{
			if($(this).offset().top <= $(window).scrollTop() + $(window).height() * 0.75 && $(this).hasClass('is-hidden'))
			{
				$(this).removeClass('is-hidden').addClass('bounce-in');
			}
		});
	});
});