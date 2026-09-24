/**
 * @param {string} Element ID
 * @param {array} Slider settings [duration, interval, stop_on_mouseover, orientation]
 */
var ContentSlider = function(element, options)
{
	this.element = element;
   
   var options = options || {};
   
   this.timer;
	this.interval = options.interval || 5000;
	this.duration = options.duration || 600;
	this.orientation = options.orientation || 'h';
	this.stop_on_mouseover = options.stop_on_mouseover || false;
   this.easing = options.easing || 'easeOutQuart';
}

/**
 * @public
 */
ContentSlider.prototype.init = function()
{
	if(this.element.find('.slide').length)
	{
      // @private
		var slider = this;
     
		this.visible_slide = this.element.find('.slide:first');

      this.visible_slide.addClass('actual');

		this.width = parseInt(this.element.find('.slides').width());
      this.height = parseInt(this.element.find('.slides').height());

      // set position of slides
		this.element.find('.slide').css({
			position: 'absolute',
			left: '0%',
			top: 0
		});
      
      // set position of 1st slide
      if(this.orientation == 'v')
      {
         this.element.find('.slide:not(:first)').css({
   			top: this.height + 'px'
   		});
      }
      else
      {
         this.element.find('.slide:not(:first)').css({
   			left: '100%'
   		});
      }

      this.element.find('.slide-button:first').addClass('slc');
      this.element.find('.link-to-show:first').addClass('slc');

      // stop on mouseover
      if(this.stop_on_mouseover)
      {
   		this.element.find('.slides').mouseenter(function(){
            slider.stopped = true;
   			clearTimeout(slider.timer);
   		})
   		.mouseleave(function(){
            slider.stopped = false;
   			slider.setTimer();
   		});
      }
      
      // slide on button click
      if(this.element.find('.slide-button').length > 1)
      {
         this.element.find('.slide-button').click(function()
         {
            var index = $(this).index();
            
            if(index != -1)
            {
               clearTimeout(slider.timer);
               slider.slide(slider.element.find('.slide:eq('+index+')'));
            }
             
            return false;
         });
      }
      
      // slide on button (prev) click
      if(this.element.find('.slide-button-prev').length)
      {
         this.element.find('.slide-button-prev').click(function()
         {
            clearTimeout(slider.timer);
            slider.slide(slider.getPrevSlide(), true);
             
            return false;
         });
      }
      
      // slide on button (next) click
      if(this.element.find('.slide-button-next').length)
      {
         this.element.find('.slide-button-next').click(function()
         {
            clearTimeout(slider.timer);
            
            slider.slide(slider.getNextSlide());
             
            return false;
         });
      }

      this.element.find('.slides').swipe(
      {
         swipeLeft: function(event, direction, distance, duration, fingerCount, fingerData)
         {
            clearTimeout(slider.timer);
            
            slider.slide(slider.getNextSlide());
             
            return false;
         },
         swipeRight: function(event, direction, distance, duration, fingerCount, fingerData)
         {
            clearTimeout(slider.timer);
            
            slider.slide(slider.getPrevSlide(), true);
             
            return false;
         }
      });

		this.setTimer();
	}
};

/**
 * @private
 */
ContentSlider.prototype.getPrevSlide = function()
{
	return this.visible_slide.prev('.slide').length ?
		this.visible_slide.prev('.slide') : this.element.find('.slide:last');
}

/**
 * @private
 */
ContentSlider.prototype.getNextSlide = function()
{
	return this.visible_slide.next('.slide').length ?
		this.visible_slide.next('.slide') : this.element.find('.slide:first');
}

/**
 * @private
 */
ContentSlider.prototype.slide = function(next_slide, reverse)
{
	if(this.visible_slide.is(':not(:animated)'))
	{
      if(this.visible_slide.index() != next_slide.index())
      {
         var object = this;

         // vertical slide
         if(this.orientation == 'v')
         {
            this.visible_slide.animate({
      			top: ((reverse) ? '' : '-') + this.height + 'px'
      		}, object.duration, object.easing);
      
      		next_slide
               .animate({
         			top: ((reverse) ? '-' : '') + this.height + 'px'
         		}, 0)
               .animate({
         			top: 0
         		}, object.duration, object.easing);
         }
         // horizontal slide
         else
         {
            this.visible_slide
               .animate({
         			left: ((reverse) ? '' : '-') + '100%'
         		}, object.duration, object.easing, function()
               {
                  object.element.find(".slide:not(:eq("+(next_slide.index())+"))").css({
                     left: ((reverse) ? '-' : '') + '100%'
                  })
               });
      
      		next_slide
               .css({
         			left: ((reverse) ? '-' : '') + '100%'
         		})
               .animate({
         			left: '0%'
         		}, object.duration, object.easing);

            next_slide.find(".slide-delay").each(function()
            {
               var delay = $(this).data("delay");

               if(delay)
               {
                  $(this)
                     .css({"margin-left": ((reverse) ? '-' : '') + "100%"})
                     .animate({"margin-left": "0"}, delay, object.easing);
               }
            });
         }
         
         this.element.find('.slide-button:eq('+this.visible_slide.index()+')').removeClass('slc');
         this.element.find('.slide-button:eq('+next_slide.index()+')').addClass('slc');

         this.element.find('.link-to-show:eq('+this.visible_slide.index()+')').removeClass('slc');
         this.element.find('.link-to-show:eq('+next_slide.index()+')').addClass('slc');
   
   		this.visible_slide = next_slide;

         this.element.find('.slide').removeClass('actual');
         this.visible_slide.addClass('actual');
   
   		this.setTimer();
      }
	}
}

/**
 * @private
 */
ContentSlider.prototype.setTimer = function()
{
	if(!this.stopped)
	{
		// @private
		var slider = this;

		this.timer = setTimeout(function(){
			slider.slide(slider.getNextSlide());
		}, this.interval);
	}
}