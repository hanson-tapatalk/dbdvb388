/*
 * CometChat
 * Copyright (c) 2014 Inscripts - support@cometchat.com | http://www.cometchat.com | http://www.inscripts.com
*/

(function($){
    $.cctapatalk = (function () {
			return {
				init:function (argument) {
					jqcc(document).ready(function(){
						jqcc.cctapatalk.Addlistener();
					});
				},
				Addlistener: function(){
					jqcc('.cc_chatwith').each(function(i,ob){
						jqcc(ob).mouseover(function(){
							jqcc(this).removeClass("vbmenu_option").removeClass("vbmenu_option_alink").addClass("vbmenu_hilite_alink").addClass("vbmenu_hilite");
						});
						jqcc(ob).mouseout(function(){
							jqcc(this).addClass("vbmenu_option").addClass("vbmenu_option_alink").removeClass("vbmenu_hilite_alink").removeClass("vbmenu_hilite");
						});
					});
				}
			};
    })();

})(jqcc);