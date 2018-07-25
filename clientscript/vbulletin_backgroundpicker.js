/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 3.8.11
|| # ---------------------------------------------------------------- # ||
|| # Copyright �2000-2017 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| #        www.vbulletin.com | www.vbulletin.com/license.html        # ||
|| #################################################################### ||
\*======================================================================*/
vBulletin.events.systemInit.subscribe(function(){init_background_picker_page("profileform")});var background_picker;function vB_BackgroundPicker(){this.bgid="backgroundpicker";this.previewid="";this.inputid="";this.selectobj=null;this.activealbum=null;this.highlightobj=null;this.albumid=0;this.init()}vB_BackgroundPicker.prototype.init=function(){this.select_handler=new vB_Select_Overlay_Handler(this.bgid);YAHOO.util.Event.on(this.bgid+"_close","click",this.close_click,this,true);this.selectobj=fetch_object("backgroundpicker_select");if(this.selectobj){this.albumid=this.selectobj.options[this.selectobj.selectedIndex].value;this.activealbum=fetch_object("usercss_background_container_"+this.albumid);YAHOO.util.Event.on(this.selectobj,"change",this.switch_backgrounds,this,true)}var A=fetch_tags(fetch_object(this.bgid),"li");for(var B=0;B<A.length;B++){if(A[B].id&&A[B].id.substr(0,8)=="usercss_"){YAHOO.util.Event.on(A[B].id,"click",this.insert_image);A[B].pictureid=A[B].id.replace(/usercss_background_image_/,"")}}};vB_BackgroundPicker.prototype.insert_image=function(B){var A=fetch_object(background_picker.inputid);A.value="albumid="+background_picker.albumid+"&pictureid="+this.pictureid;background_picker.close()};vB_BackgroundPicker.prototype.switch_backgrounds=function(A){this.activealbum.style.display="none";this.albumid=this.selectobj.options[this.selectobj.selectedIndex].value;this.activealbum=fetch_object("usercss_background_container_"+this.albumid);this.activealbum.style.display=""};vB_BackgroundPicker.prototype.open=function(F){this.toggle_highlight("off");if(F){this.clickid=F;this.inputid=F.replace(/_chooser/,"")}var M=fetch_object(this.clickid);var I=YAHOO.util.Dom.getX(M);var C=YAHOO.util.Dom.getY(M)+M.offsetHeight;var E=fetch_object(this.inputid);var B=null;var J=null;var G=E.value.match(/albumid=(\d+)/);if(G){J=G[1]}var L=E.value.match(/pictureid=(\d+)/);if(L){B=L[1]}if(J&&B){var D=fetch_object("usercss_background_container_"+J);if(D){var K=fetch_tags(D,"li");for(var H=0;H<K.length;H++){if(K[H].id&&K[H].id.substr(0,8)=="usercss_"){if(K[H].pictureid==B){this.highlightobj=fetch_object("usercss_background_item_"+B);this.toggle_highlight("on");if(this.albumid!=J){for(H=0;H<this.selectobj.options.length;H++){if(this.selectobj.options[H].value==J){this.selectobj.selectedIndex=H;break}}this.activealbum.style.display="none";this.albumid=J;this.activealbum=fetch_object("usercss_background_container_"+this.albumid);this.activealbum.style.display=""}break}}}}}fetch_object("usercss_background_container_"+this.albumid);var A=fetch_object(this.bgid);A.style.left=I+"px";A.style.top=C+"px";A.style.display="";if(I+A.offsetWidth>document.body.clientWidth){I-=I+A.offsetWidth-document.body.clientWidth;A.style.left=I+"px"}this.select_handler.hide()};vB_BackgroundPicker.prototype.toggle_highlight=function(A){if(this.highlightobj){if(A=="on"){YAHOO.util.Dom.addClass(this.highlightobj,"box_selected");YAHOO.util.Dom.removeClass(this.highlightobh,"box")}else{if(A=="off"){YAHOO.util.Dom.addClass(this.highlightobj,"box");YAHOO.util.Dom.removeClass(this.highlightobj,"box_selected")}}}};vB_BackgroundPicker.prototype.close=function(){fetch_object(this.bgid).style.display="none";background_picker.toggle_highlight("off");this.select_handler.show()};vB_BackgroundPicker.prototype.close_click=function(A){this.close()};vB_BackgroundPicker.prototype.getAncestorOrThisByClassName=function(B,A){if(B.className&&B.className==A){return B}else{return YAHOO.util.Dom.getAncestorByClassName(B,A)}};function init_background_picker_page(D){var A;var B=fetch_tags(fetch_object(D),"input");for(var C=0;C<B.length;C++){if(B[C].id&&B[C].id.substr(0,8)=="usercss_"){A=fetch_object(B[C].id+"_chooser");if(A){A.style.display="";YAHOO.util.Event.on(A,"click",display_background_picker)}}}}function display_background_picker(){if(!background_picker){background_picker=new vB_BackgroundPicker()}if(typeof (color_picker)!="undefined"){color_picker.close()}background_picker.open(this.id)};