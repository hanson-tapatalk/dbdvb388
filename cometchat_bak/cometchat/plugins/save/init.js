<?php

		include_once(dirname(__FILE__).DIRECTORY_SEPARATOR."lang".DIRECTORY_SEPARATOR."en.php");

		if (file_exists(dirname(__FILE__).DIRECTORY_SEPARATOR."lang".DIRECTORY_SEPARATOR.$lang.".php")) {
			include_once(dirname(__FILE__).DIRECTORY_SEPARATOR."lang".DIRECTORY_SEPARATOR.$lang.".php");
		}

		foreach ($save_language as $i => $l) {
			$save_language[$i] = str_replace("'", "\'", $l);
		}
?>

/*
 * CometChat
 * Copyright (c) 2014 Inscripts - support@cometchat.com | http://www.cometchat.com | http://www.inscripts.com
*/

(function($){   
  
	$.ccsave = (function () {

		var title = '<?php echo $save_language[0];?>';
		
        return {

			getTitle: function() {
				return title;	
			},

			init: function (id) {	                             
                                var currentTime = new Date();
                                var monthNames = [ "Jan", "Feb", "Mar", "Apr", "May", "Jun",
                                "July", "Aug", "Sep", "Oct", "Nov", "Dec" ];
                                var month = currentTime.getMonth();
                                var day = currentTime.getDate();
                                var year = currentTime.getFullYear();
                                var type = 'th';
                                if(day==1||day==21||day==31){
                                    type = 'st';
                                }else if(day==2||day==22){
                                    type = 'nd';
                                }else if(day==3||day==23){
                                    type = 'rd';
                                }
                                var today = monthNames[month] + " " + day + type + " " + year;
                                baseUrl = $.cometchat.getBaseUrl();
                                baseData = $.cometchat.getBaseData();
                                <?php if($type=='module'&&$name=='chatrooms'): ?>
                                var roomname = jqcc.cometchat.getChatroomVars('currentroomname');
                                var themename = jqcc.cometchat.getChatroomVars('themename');
                                if ($("#currentroom_convotext").html() != '') {
                                    var filename = 'Conversation in '+roomname+' chatroom on '+today;
									$("#currentroom").find("span.cometchat_chatboxmessagefrom").before('<div class="cc_newline" style="display:none;">\n<\div>');
									$('div.cometchat_chatboxmessage').find('img.cometchat_smiley').each(function(key,value){
										$(this).before('<div class="cc_newline_smile"  style="display:none">('+$(this).attr('title')+')<\div>');
									});
									var content = $("#currentroom_convotext").find(".cometchat_chatboxmessage").text();
									var deletemsg = '<?php echo $chatrooms_language[46];?>';
									deletemsg ="\\(" + deletemsg + "\\)";							
									content = content.replace(new RegExp(deletemsg, "g"), "");
									$('div.cc_newline').remove();
									$('div.cc_newline_smile').remove();
									$('#cc_saveconvochatroom').remove();
									setTimeout(function(){		
										$('<form id = "ccsaveform" action="" method="post">'+
										'<input type="hidden" name="roomname" />'+
										'<input type="hidden" name="content" />'+
										'<input type="hidden" name="filename" />'+
										'</form>').appendTo('body');;
										
										var form = $('#ccsaveform');
										form.attr('action',baseUrl+'plugins/save/index.php?id='+roomname+'&basedata='+baseData);
										form.find('input[name=roomname]').val(roomname);
										form.find('input[name=content]').val(content);
										form.find('input[name=filename]').val(filename);
										form.submit();
                                    },50);
                                } else {
                                        alert('<?php echo $save_language[1];?>');
                                }
                                <?php else: ?>   
                                var cometchat_user_popup = $("#cometchat_user_"+id+"_popup");
                                if (cometchat_user_popup.find("div.cometchat_tabcontenttext").html() != '') {
                                    var username = $.cometchat.getName(id);
                                    var filename = 'Conversation with '+username+' on '+today;
                                    var settings = jqcc.cometchat.getSettings();
                                    if (settings.theme == 'hangout') {
                                        var other = cometchat_user_popup.find("div.cometchat_name").text();
                                        cometchat_user_popup.find("div.cometchat_other").before('<div class="cc_newline"  style="display:none">\n'+other+': <\div>');
                                        cometchat_user_popup.find("div.cometchat_self").before('<div class="cc_newline"  style="display:none">\nMe: <\div>');
                                        $('div.cometchat_other').find("img.cometchat_smiley").each(function(key,value){
                                            $(this).before('<div class="cc_newline_smile"  style="display:none">('+$(this).attr('title')+')<\div>');
                                        });
                                        $('div.cometchat_self').find("img.cometchat_smiley").each(function(key,value){
                                            $(this).before('<div class="cc_newline_smile"  style="display:none">('+$(this).attr('title')+')<\div>');
                                        });
                                        var content = cometchat_user_popup.find("div.cometchat_tabcontenttext").text().trim();
                                        $('.cc_newline').remove();
                                        $('.cc_newline_smile').remove();
                                        $('.cc_saveconvoframe').remove();
										$('#cc_saveconvochatroom').remove();
                                    } else {
                                        cometchat_user_popup.find("div.cometchat_chatboxmessage").before('<div class="cc_newline">\n<\div>');
                                        $('div.cometchat_chatboxmessage').find("img.cometchat_smiley").each(function(key,value){
                                            $(this).before('<div class="cc_newline_smile"  style="display:none">('+$(this).attr('title')+')<\div>');
                                        });
                                        var content = cometchat_user_popup.find(".cometchat_tabcontenttext").text().trim();
                                        $('div.cc_newline').remove();
                                        $('div.cc_newline_smile').remove();
                                        $('iframe.cc_saveconvoframe').remove();
										$('#cc_saveconvochatroom').remove();
                                    }
                                    var iframe = $('<iframe id="cc_saveconvoframe'+id+'" class="cc_saveconvoframe" frameborder="0" style="width: 1px; height: 1px; display: none;"></iframe>').appendTo('body');

                                    setTimeout(function(){						 
										var formHTML = '<form action="" method="post">'+
										'<input type="hidden" name="username" />'+
										'<input type="hidden" name="content" />'+
										'<input type="hidden" name="filename" />'+
										'</form>';
										var body = (iframe.prop('contentDocument') !== undefined) ?
																iframe.prop('contentDocument').body :
																iframe.prop('document').body;
										body = $(body);
										body.html(formHTML);
										var form = body.find('form');
										form.attr('action',baseUrl+'plugins/save/index.php?id='+id+'&basedata='+baseData);
										form.find('input[name=username]').val(username);
										form.find('input[name=content]').val(content);
										form.find('input[name=filename]').val(filename);
										form.submit();
                                    },50);
                                } else {
                                        alert('<?php echo $save_language[1];?>');
                                }
                                <?php endif; ?>
			}

        };
    })();
 
})(jqcc);