
var LightBox = function(element, trigger, options)
{
   options = options || {};

   this.element = element;
   this.trigger = trigger;
   this.overlay = $("#my_lbox_overlay");
   this.visible = false;
   this.noSetPosition = options.noSetPosition || false;
   this.onShow = options.onShow || function(){ };
   this.onHide = options.onHide || function(){ };
   this.afterShow = options.afterShow || function(){ };
   this.afterHide = options.afterHide || function(){ };
   
   var object = this;

   if(object.element.hasClass('open'))
   {
      object.show();
   }
   else
   {
      //object.element.hide();
   }

   object.trigger.on('click', function(e)
   {
      object.trigger = $(this);

      e.preventDefault();
      object.show();
   });

   object.element.find(".ico_close").on('click', function(e)
   {
      e.preventDefault();
      object.hide();
   });

   object.element.find(".close").on('click', function(e)
   {
      e.preventDefault();
      object.hide();
   });

   object.overlay.on('click', function(e)
   {
      object.hide();
   });

   this.setPosition(!this.noSetPosition);
}

LightBox.prototype.setPosition = function(setLeft)
{
   var height = this.element.outerHeight(true);
   var width = this.element.outerWidth(true) / 2;

   var scrollTop = $(window).scrollTop(); 

   var offset = ($(".my_lbox:visible").length * 40);

   this.element.css({
      position: 'absolute',
      top: (scrollTop + 80)+'px',
      marginTop: '0'
   });

   if(setLeft)
   {
      this.element.css({
         left: '50%',
         margin: '0px 0 0 -' + (width) + 'px'
      });
   }
};

LightBox.prototype.show = function()
{
   if($(window).width() <= this.element.width())
   {
      //return false;
   }

   var object = this;

   this.onShow(object.element, object.trigger);
   this.setPosition(!this.noSetPosition);
   this.overlay.fadeIn(200, function()
   {
      object.afterShow(object.element, object.trigger);
   });
   this.element.css({}).fadeIn(200);
   this.visible = true;
};

LightBox.prototype.hide = function()
{
   var object = this;

   this.onHide(object.element, object.trigger);
   this.overlay.fadeOut(200);
   this.element.fadeOut(200, function()
   {
      object.afterHide(object.element, object.trigger);
   });
   this.visible = false;
};