(function($){
    $.cctapatalk = (function(){
        var settings = {};
        var baseUrl;
        var language;
        var trayicon;
        var typingTimer;
        var resynchTimer;
        var notificationTimer;
        var chatboxOpened = {};
        var allChatboxes = {};
        var chatboxDistance = 7;
        var visibleTab = [];
        var blinkInterval;
        var trayWidth = 0;
        var siteOnlineNumber = 0;
        var olddata = {};
        var tooltipPriority = 0;
        var desktopNotifications = {};
        var webkitRequest = 0;
        var chatbottom = [];
        var resynch = 0;
        var reload = 0;
        var lastmessagetime = Math.floor(new Date().getTime());
        var favicon;
        var checkfirstmessage;
        var bg1 = "rgb(237, 239, 244) linear-gradient(rgb(241, 214, 34), rgb(204, 173, 21)) repeat scroll 0% 0% / auto padding-box border-box";
        var bg2 = 'rgb(241, 214, 34)';
        var bgs = [bg1,bg2];
        var flickCtr = 0;
        var flick = -1;
        var chatboxHeight = parseInt('<?php echo $chatboxHeight; ?>');
        var chatboxWidth = parseInt('<?php echo $chatboxWidth; ?>');
        var barVisiblelimit = (chatboxWidth + chatboxDistance + 14);
        var usersThread = {};
        var removeOpenChatboxId = function(id){
            var openChatBoxIds = jqcc.cometchat.getSessionVariable('openChatboxId').split(',');
            openChatBoxIds.splice(openChatBoxIds.indexOf(id), 1);
            jqcc.cometchat.setSessionVariable('openChatboxId', openChatBoxIds.join(','));
            jqcc.cometchat.setThemeVariable('openChatboxId', openChatBoxIds);
        };
        var addOpenChatboxId = function(id){
            var openChatBoxIds = jqcc.cometchat.getSessionVariable('openChatboxId').split(',');
            if(openChatBoxIds.indexOf(id)>-1){
                return;
            }
            if(openChatBoxIds[0]==""){
                openChatBoxIds[0] = id;
            }else{
                openChatBoxIds.push(id);
            }
            jqcc.cometchat.setSessionVariable('openChatboxId', openChatBoxIds.join(','));
            jqcc.cometchat.setThemeVariable('openChatboxId', openChatBoxIds);
        };
        return {
            setUsersThread: function(id,value){
                usersThread[id] = value;
                jqcc.cometchat.setThemeArray('buddylistName',id,value);
            },
            getUsersThread:function(id){
                return usersThread[id];
            },
            playSound: function(){
                var flag = 0;
                try{
                    if(settings.messageBeep==1&&settings.beepOnAllMessages==0&&checkfirstmessage==1){
                        flag = 1;
                    }
                    if((settings.messageBeep==1&&settings.beepOnAllMessages==1)||flag==1){
                        document.getElementById('messageBeep').play();
                    }
                }catch(error){
                    messageBeep = 0;
                }
            },
            initialize: function(){
                settings = jqcc.cometchat.getSettings();
                baseUrl = jqcc.cometchat.getBaseUrl();
                if(typeof jqcc.cometchat.getLanguage != 'undefined'){
                    language = jqcc.cometchat.getLanguage();
                }else{
                    language = jqcc.cometchat.getLanguageAll();
                }

                trayicon = jqcc.cometchat.getTrayicon();

                var trayData = '';
                if(settings.windowFavicon==1){
                    favicon = new Favico({
                        animation: 'pop'
                    });
                }
                $("body").append('<div id="cometchat"></div><div id="cometchat_hidden"><div id="cometchat_hidden_content"></div></div><div id="cometchat_tooltip"><div class="cometchat_tooltip_content"></div></div>');
                if(settings.showModules==1){
                    trayData += '<div class="cometchat_tabsubtitle">';
                    trayData += '<span id="cometchat_edit">'+language[78]+'</span><span id="cometchat_plus"></span>';
                    trayData += '</div>';
                }

                if(language[1] == 'Powered By <a href="http://www.cometchat.com">CometChat</a>'){
                    trayData += '<div class="cometchat_power">'+language[1]+'</div>';
                }

                var cc_state = $.cookie(settings.cookiePrefix+'state');
                var number = 0;
                if(cc_state!=null){
                    var cc_states = cc_state.split(/:/);
                    number = cc_states[3];
                }
                var optionsbutton = '';
                var optionsbuttonpop = '';
                var usertab = '';
                var usertabpop = '';
                var findUser = '<div id="cometchat_searchbar" class="cometchat_floatL"><div id="cometchat_searchbar_icon"><div class="after"></div></div><input type="text" name="cometchat_search" class="cometchat_search cometchat_search_light textInput" id="cometchat_search" value="'+language[18]+'"></div>';
                if(settings.showSettingsTab==1){
                    optionsbutton = '<div id="cometchat_optionsbutton" class="cometchat_tab cometchat_floatL"><div id="cometchat_optionsbutton_icon" class="cometchat_optionsimages"></div></div>';
                    optionsbuttonpop = '<div id="cometchat_optionsbutton_popup" class="cometchat_tabpopup" style="display:none"><div class="cometchat_userstabtitle"><div id="cometchat_statuschange"></div><div class="cometchat_userstabtitletext">'+language[0]+'</div><div class="cometchat_minimizebox cometchat_tooltip" id="cometchat_minimize_optionsbutton_popup" data-title="'+language[63]+'"></div><br clear="all"/></div><div class="cometchat_tabsubtitle">'+language[1]+'</div><div class="cometchat_tabcontent cometchat_optionstyle"><div id="guestsname"><strong>'+language[43]+'</strong><br/><input type="text" class="cometchat_guestnametextbox"/><div class="cometchat_guestnamebutton">'+language[44]+'</div></div><strong>'+language[2]+'</strong><br/><textarea class="cometchat_statustextarea"></textarea><div class="cometchat_statusbutton">'+language[22]+'</div><div class="cometchat_statusinputs"><strong>'+language[23]+'</strong><br/><span class="cometchat_user_available"></span><span class="cometchat_optionsstatus available">'+language[3]+'</span><span class="cometchat_optionsstatus2 cometchat_user_invisible" ></span><span class="cometchat_optionsstatus invisible">'+language[5]+'</span><div style="clear:both"></div><span class="cometchat_optionsstatus2 cometchat_user_busy"></span><span class="cometchat_optionsstatus busy">'+language[4]+'</span><span class="cometchat_optionsstatus2 cometchat_user_invisible"></span><span class="cometchat_optionsstatus cometchat_gooffline">'+language[11]+'</span><br clear="all"/></div><div class="cometchat_options_disable"><div><input type="checkbox" id="cometchat_soundnotifications" style="vertical-align: -2px;">'+language[13]+'</div><div style="clear:both"></div><div><input type="checkbox" id="cometchat_popupnotifications" style="vertical-align: -2px;">'+language[24]+'</div></div></div></div>';
                }


                if(settings.showOnlineTab==1){
                    usertab = '<span id="cometchat_userstab" class="cometchat_tab"><span id="cometchat_userstab_icon"></span><span id="cometchat_userstab_text">'+language[81]+' ('+jqcc.tapatalk.updateMessageCount(1)+')</span></span>';
                    usertabpop = '<div id="cometchat_userstab_popup" class="cometchat_tabpopup"><div class="cometchat_userstabtitle"><div id="cometchat_statuschange"></div><div class="cometchat_userstabtitletext">'+language[81]+'</div><div id="cometchat_close_cc">X</div><div class="cometchat_minimizebox cometchat_tooltip" id="cometchat_minimize_userstab_popup" data-title="'+language[62]+'"></div><br clear="all"/></div>'+trayData+'<div class="cometchat_tabcontent cometchat_tabstyle"><div id="cometchat_userscontent"><div id="cometchat_userslist"><div class="cometchat_nofriends">'+language[41]+'</div></div></div></div></div>';
                }
                var statusOptions='<ul><li class="cc_status_options"><span class="cc_state cc_available"></span>Available</li><li class="cc_status_options"><span class="cc_state cc_away"></span>Away</li><li class="cc_status_options"><span class="cc_state cc_busy"></span>Do Not Disturb</li><li class="cc_status_options"><span class="cc_state cc_offline"></span>Offline</li></ul>';
                var baseCode = '<div id="cometchat_base"><div id="loggedout" class="cometchat_optionsimages_exclamation cometchat_tab cometchat_tooltip" data-title="'+language[8]+'"></div><div id="cometchat_sidebar"> <div id="cometchat_statusoptions">'+statusOptions+'</div>'+usertabpop+'</div>'+optionsbuttonpop+''+usertab+'<div id="cometchat_chatboxes"><div id="cometchat_chatboxes_wide" class="cometchat_floatR"></div></div><div id="cometchat_chatbox_left" class="cometchat_tab"><div class="cometchat_tabalertlr"></div><div class="cometchat_tabtext">0</div><div id="cometchat_unseenUserCount"></div><div id="cometchat_chatbox_left_border_fix"></div></div><div id="cometchat_unseenUsers"></div></div>';
                document.getElementById('cometchat').innerHTML = baseCode;
                if(settings.showSettingsTab==0){
                    $('#cometchat_userstab').addClass('cometchat_extra_width');
                    $('#cometchat_userstab_popup').find('div.cometchat_tabstyle').addClass('cometchat_border_bottom');
                }
                if(jqcc().slimScroll){
                    $('#cometchat_userscontent').slimScroll({height: '100%'});
                }
                jqcc[settings.theme].optionsButton();
                jqcc[settings.theme].chatTab();

                $('#cometchat_chatboxes').attr('unselectable', 'on').css('MozUserSelect', 'none').bind('selectstart.ui', function(){
                    return false;
                });
                $('#cometchat_userscontent').attr('unselectable', 'on').css('MozUserSelect', 'none').bind('selectstart.ui', function(){
                    return false;
                });
                $('div.cometchat_trayicon').attr('unselectable', 'on').css('MozUserSelect', 'none').bind('selectstart.ui', function(){
                    return false;
                });
                $('div.cometchat_tab').attr('unselectable', 'on').css('MozUserSelect', 'none').bind('selectstart.ui', function(){
                    return false;
                });
                $('#cometchat_chatbox_left').bind('click', function(){
                    $(this).toggleClass('cometchat_unseenList_open');
                    $('#cometchat_unseenUsers').toggle();
                    $('#cometchat_chatbox_left_border_fix').toggle();
                });
                jqcc[settings.theme].windowResize();
                jqcc[settings.theme].scrollBars();
                $('#cometchat_chatbox_left').mouseover(function(){
                    $(this).addClass("cometchat_chatbox_lr_mouseover");
                });
                $('#cometchat_chatbox_left').mouseout(function(){
                    $(this).removeClass("cometchat_chatbox_lr_mouseover");
                });
                $('#cometchat_unseenUsers').on('click','.cometchat_unseenClose',function(e){
                    e.stopImmediatePropagation();
                    var parentElem = $(this).parent();
                    var uid = parentElem.attr('uid');
                    parentElem.remove();
                    $('#cometchat_user_'+uid).find('.cometchat_closebox_bottom').click();
                    $.tapatalk.rearrange();
                });

                $('#cometchat_unseenUsers').on('click','.cometchat_unseenUserList',function(){
                    var uid = $(this).attr('uid');
                    $.tapatalk.swapTab(uid);
                });
                $('body').on('mouseover','.cometchat_tooltip',function(){
                    var currElem = $(this);
                    var tId = currElem.attr('id');
                    var tMsg = currElem.data('title');
                    if(tMsg != null){
                        $.tapatalk.tooltip(tId, tMsg);
                    }
                });
                $('body').on('mouseout','.cometchat_tooltip',function(){
                    $.tapatalk.closeTooltip();
                });
                $(window).bind('resize', function(){
                    jqcc[settings.theme].windowResize();
                });
                if(typeof document.body.style.maxHeight==="undefined"){
                    jqcc[settings.theme].scrollFix();
                        $(window).bind('scroll', function(){
                        jqcc[settings.theme].scrollFix();
                    });
                }else if(jqcc.cometchat.getThemeVariable('mobileDevice')){
                    if(settings.disableForMobileDevices){
                        $('#cometchat').css('display', 'none');
                        jqcc.cometchat.setThemeVariable('runHeartbeat', 0);
                    }
                }
                document.onmousemove = function(e){
                    var nowTime = new Date();
                    jqcc.cometchat.setThemeVariable('idleTime', Math.floor(nowTime.getTime()/1000));
                };

                var extlength = settings.extensions.length;
                if(extlength>0){
                    for(var i = 0; i<extlength; i++){
                        var name = 'cc'+settings.extensions[i];
                        if(typeof ($[name])=='object'){
                            $[name].init();
                        }
                    }
                }
                if($.inArray('block', settings.plugins)>-1){
                    $.ccblock.addCode();
                }

                /* custom */
                $('div#cometchat_statuschange').on('click',function(e){
                    $('div#cometchat_statusoptions').fadeToggle('fast');
                });
                $('li.cc_status_options').on('click',function(e){
                    var txt = $(this).text();

                    switch(txt){
                        case 'Available':
                            $('div#cometchat_statuschange').css('background-image','url("'+baseUrl+'themes/tapatalk/images/custom/available.png")');
                            jqcc.cometchat.sendStatus('available');
                            break;
                        case 'Away':
                            $('div#cometchat_statuschange').css('background-image','url("'+baseUrl+'themes/tapatalk/images/custom/away.png")');
                            jqcc.cometchat.sendStatus('away');
                            break;
                        case 'Do Not Disturb':
                            $('div#cometchat_statuschange').css('background-image','url("'+baseUrl+'themes/tapatalk/images/custom/dnd.png")');
                            jqcc.cometchat.sendStatus('busy');
                            break;
                        case 'Offline':
                            $('div#cometchat_statuschange').css('background-image','url("'+baseUrl+'themes/tapatalk/images/custom/offline.png")');
                            jqcc.cometchat.sendStatus('offline');
                            break;
                    }
                });
                $('span#cometchat_edit').on('click',function(e){
                    var txt = $('span#cometchat_edit').text();
                    if(txt === ''+language[78]+''){
                        $('span#cometchat_edit').text('Done');
                        $('div.cometchat_userstabtitletext').text(''+language[78]+'');
                        $('span#cometchat_lastconvo').hide();
                        $('span#cometchat_chatarrow').hide();
                        $('p#cometchat_lastmessage').hide();
                    }
                    else{
                        $('span#cometchat_edit').text(''+language[78]+'');
                        jqcc.tapatalk.updateMessageCount();
                        $('span#cometchat_lastconvo').show();
                        $('span#cometchat_chatarrow').show();
                        $('p#cometchat_lastmessage').show();
                    }
                    $('span.cometchat_cross').fadeToggle('fast');
                });
                $('span#cometchat_plus').on('click',function(e){
                    var txt = $('span#cometchat_plus').text();

                    if(txt !== ''+language[80]+''){
                        $('span#cometchat_edit').text('');
                        $('div.cometchat_userstabtitletext').text('New Messages');
                        $('span#cometchat_plus').text(''+language[80]+'');
                        $('span#cometchat_plus').css('background-image','none');
                        var buddylist = usersThread;
                        forumMember = '';
                        for(var key in buddylist) {
                            user_name = buddylist[key];
                            user_id = parseInt(key);
                            user_a = jqcc.cometchat.getThemeArray('buddylistAvatar',user_id);
                            if(user_a == 'undefined' || typeof user_a == 'undefined'){
                                user_a = baseUrl+'themes/tapatalk/images/custom/default_avatar.png';
                            }
                            user_s = jqcc.cometchat.getThemeArray('buddylistStatus',user_id);
                            if(user_s == 'undefined' || typeof user_s === 'undefined'){
                                user_s = 'offline';
                            }
                            var offline = '';
                            if(user_s == 'offline'){
                                offline = 'offline_treatment';
                            }
                            forumMember += '<div id="cometchat_userlist_'+user_id+'" class="cometchat_userlist '+offline+'" onmouseover="jqcc(this).addClass(\'cometchat_userlist_hover\');" onmouseout="jqcc(this).removeClass(\'cometchat_userlist_hover\');"><span class="cometchat_cross" onclick="jqcc.tapatalk.deleteConversation('+user_id+')" ></span><span class="cometchat_userscontentavatar"><img class="cometchat_userscontentavatarimage" src="'+user_a+'"><span class="cometchat_userscontentdot cometchat_'+user_s+'"></span></span><span class="cometchat_userscontentname" style="padding-top:8px;"><b>'+user_name+'</b></span></div><div id="cometchat_divider"></div>';

                        }
                        forumMember = '<div><div class="thread_users">These Users are from this Thread</div>'+forumMember+'</div>';
                        $('div#cometchat_userslist').html(forumMember);
                        $('div.cometchat_userlist').bind('click', function(e){
                            jqcc.cometchat.userClick(e.target);
                        });
                    } else{
                        $('span#cometchat_plus').text('');
                        $('span#cometchat_plus').css('background-image','url("'+baseUrl+'themes/tapatalk/images/custom/plus.png")');
                        $('span#cometchat_edit').text(''+language[78]+'');
                        jqcc.tapatalk.updateMessageCount();
                        $.ajax({
                            url: baseUrl+"cometchat_receive.php",
                            data: {"buddylist":'1' , "initialize":'1'},
                            dataType: 'jsonp',

                            error: function(){
                                console.log('error');
                            },
                            success: function(data){
                                console.log(data);
                                if(data){
                                    $.each(data, function(type, item){
                                        if(type=='buddylist'){
                                            jqcc.tapatalk.buddyList(item);
                                        }
                                    });
                                }
                            }

                        });
                    }
                });
                //init status - online
                jqcc.cometchat.sendStatus('available');
                var currentUrl = document.URL;
                var res = currentUrl.match(/showthread.php?/g);
                if(res == null || res === null || res == 'undefined'){
                    $('span#cometchat_plus').css('display','none');
                }

            },
            newAnnouncement: function(item){
                if($.cookie(settings.cookiePrefix+"popup")&&$.cookie(settings.cookiePrefix+"popup")=='true'){
                }else{
                    tooltipPriority = 100;
                    message = '<div class="cometchat_announcement">'+item.m+'</div>';
                    if(item.o){
                        var notifications = (item.o-1);
                        if(notifications>0){
                            message += '<div class="cometchat_notification" onclick="javascript:jqcc.cometchat.launchModule(\'announcements\')"><div class="cometchat_notification_message cometchat_notification_message_and">'+language[36]+notifications+language[37]+'</div><div style="clear:both" /></div>';
                        }
                    }else{
                        $.cookie(settings.cookiePrefix+"an", item.id, {path: '/', expires: 365});
                    }
                    jqcc[settings.theme].tooltip("cometchat_userstab", message, 0);
                    clearTimeout(notificationTimer);
                    notificationTimer = setTimeout(function(){
                        $('#cometchat_tooltip').css('display', 'none');
                        tooltipPriority = 0;
                    }, settings.announcementTime);
                }
            },
            buddyList: function(item){
                var onlineNumber = 0;
                var totalFriendsNumber = 0;
                var lastGroup = '';
                var groupNumber = 0;
                var tooltipMessage = '';
                var buddylisttemp = '';
                var buddylisttempavatar = '';
                var deletePopup = '<div class="conf_popup"><div><p>'+language[77]+'<label>'+language[83]+'<input type="checkbox" name="donotShow" id="donotShow" ></label></p><span class = "conf_button">OK</span><span class = "conf_button" style="right:75px;">'+language[80]+'</span></div></div>';
                $.each(item, function(i, buddy){
                    longname = buddy.n;
                    if(chatboxOpened[buddy.id]!=null){
                        var cometchat_user_popup = $("#cometchat_user_"+buddy.id+"_popup");
                        $("#cometchat_user_"+buddy.id+", #cometchat_user_"+buddy.id+"_popup").find('div.cometchat_userscontentdot101')
                                .removeClass("cometchat_available")
                                .removeClass("cometchat_busy")
                                .removeClass("cometchat_offline")
                                .removeClass("cometchat_away")
                                .removeClass("cometchat_banned")
                                .addClass('cometchat_'+buddy.s);

                        if(cometchat_user_popup.length>0){
                            //cometchat_user_popup.find("div.cometchat_message").html(banNew);
                        }
                        $("#cometchat_user_"+buddy.id+", #cometchat_user_"+buddy.id+"_popup").find('div.cometchat_userscontentdot').show();

                        if(buddy.s == 'offline'){
                            cometchat_user_popup.find('div#cometchat_userofflineMessage_'+buddy.id).show();
                        }else{
                            cometchat_user_popup.find('div#cometchat_userofflineMessage_'+buddy.id).hide();
                        }

                    }
                    if(buddy.s!='offline'){
                        onlineNumber++;
                    }
                    totalFriendsNumber++;
                    var group = '';
                    if(buddy.g!=lastGroup&&typeof buddy.g!="undefined"){
                        if(buddy.g==''){
                            groupName = language[40];
                        }else{
                            groupName = buddy.g;
                        }
                        if(groupNumber==0){
                            group = '<div class="cometchat_subsubtitle cometchat_subsubtitle_top"><hr class="hrleft">'+groupName+'<hr class="hrright"></div>';
                        }else{
                            group = '<div class="cometchat_subsubtitle"><hr class="hrleft">'+groupName+'<hr class="hrright"></div>';
                        }
                        groupNumber++;
                        lastGroup = buddy.g;
                    }
                    var statusIndicator = '';
                    if(buddy.s == 'available') {
                        var deviceType = language[60];
                        if(buddy.d == 1){
                             deviceType = language[61];
                        }
                        statusIndicator = '<div class="cometchat_deviceType cometchat_floatR">'+deviceType+'</div>';
                    }
                    buddylisttemp += group+'<div id="cometchat_userlist_'+buddy.id+'" class="cometchat_userlist" onmouseover="jqcc(this).addClass(\'cometchat_userlist_hover\');" onmouseout="jqcc(this).removeClass(\'cometchat_userlist_hover\');"><span class="cometchat_userscontentname">'+longname+'</span><span class="cometchat_userscontentdot cometchat_'+buddy.s+'"></span></div>';
                    var lastTime = buddy.m.split('^')[0];
                    lastTime = jqcc.tapatalk.getTimeProccessed(lastTime);
                    var lastMessage = buddy.m.split('^')[1];

                    tempMsg = jqcc('<div>'+lastMessage+'</div>');
                    jqcc.each(tempMsg.find('img.cometchat_smiley'),function(){
                        jqcc(this).replaceWith('*'+jqcc(this).attr('title')+'*');
                    });
                    jqcc.each(tempMsg.find('a.imagemessage'),function(){
                        jqcc(this).replaceWith('sent you a photo');
                    });
                    lastMessage = tempMsg.text();
                    if(lastMessage.length > 15){
                        lastMessage = lastMessage.substring(0,13)+'...';
                    }
                    var offline = '';
                    if(buddy.s == 'offline'){
                        offline = 'offline_treatment';
                    }
                    lastMessage = lastMessage.replace(/\</g, '&lt;');
                     lastMessage = lastMessage.replace(/\>/g, '&gt;');
                    buddylisttempavatar += group+'<div id="cometchat_userlist_'+buddy.id+'" class="cometchat_userlist '+offline+'" onmouseover="jqcc(this).addClass(\'cometchat_userlist_hover\');" onmouseout="jqcc(this).removeClass(\'cometchat_userlist_hover\');"><span class="cometchat_cross" onclick="jqcc.tapatalk.deleteConversation('+buddy.id+')" ></span><span class="cometchat_userscontentavatar"><span class="cometchat_usersnewmessages">0</span><img class="cometchat_userscontentavatarimage" original="'+buddy.a+'"><span class="cometchat_userscontentdot cometchat_'+buddy.s+'"></span></span><span class="cometchat_userscontentname"><b>'+longname+'</b><p id ="cometchat_lastmessage"><small>'+lastMessage+'</small></p></span><span id="cometchat_chatarrow"></span><span id="cometchat_lastconvo">'+lastTime+'</span></div><div id="cometchat_divider"></div>';
                    var message = buddy.m;
                    if(settings.displayOnlineNotification==1&&jqcc.cometchat.getExternalVariable('initialize')!=1&&jqcc.cometchat.getThemeArray('buddylistStatus', buddy.id)!=buddy.s&&buddy.s=='available'){
                        message = language[19];
                    }
                    if(settings.displayBusyNotification==1&&jqcc.cometchat.getExternalVariable('initialize')!=1&&jqcc.cometchat.getThemeArray('buddylistStatus', buddy.id)!=buddy.s&&buddy.s=='busy'){
                        message = language[21];
                    }
                    if(settings.displayOfflineNotification==1&&jqcc.cometchat.getExternalVariable('initialize')!=1&&jqcc.cometchat.getThemeArray('buddylistStatus', buddy.id)!='offline'&&buddy.s=='offline'){
                        message = language[20];
                    }
                    if(message!=''){
                        tooltipMessage += '<div class="cometchat_notification" onclick="javascript:jqcc.cometchat.chatWith(\''+buddy.id+'\')"><div class="cometchat_notification_avatar"><img class="cometchat_notification_avatar_image" src="'+buddy.a+'"></div><div class="cometchat_notification_message">'+buddy.n+message+'<br/><span class="cometchat_notification_status">'+buddy.m+'</span></div><div style="clear:both" /></div>';
                    }

                    jqcc.cometchat.setThemeArray('buddylistStatus', buddy.id, buddy.s);
                    jqcc.cometchat.setThemeArray('buddylistMessage', buddy.id, buddy.m);
                    jqcc.cometchat.setThemeArray('buddylistName', buddy.id, buddy.n);
                    jqcc.cometchat.setThemeArray('buddylistAvatar', buddy.id, buddy.a);
                    jqcc.cometchat.setThemeArray('buddylistLink', buddy.id, buddy.l);
                    jqcc.cometchat.setThemeArray('buddylistIsDevice', buddy.id, buddy.d);

                });
                if(groupNumber>0){
                    $('.cometchat_subsubtitle_siteusers').css('display', 'none');
                }
                var bltemp = buddylisttempavatar;
                if(totalFriendsNumber>settings.thumbnailDisplayNumber){
                    bltemp = buddylisttemp;
                }
                if(document.getElementById('cometchat_userslist')){
                    if(buddylisttempavatar!=''){
                        if($('#cometchat_edit').text() == 'Edit' && $('#cometchat_plus').text() == ''){
                            document.getElementById('cometchat_userslist').style.display = 'block';
                            jqcc.cometchat.replaceHtml('cometchat_userslist', '<div>'+deletePopup+bltemp+'</div>');
                            jqcc.tapatalk.updateMessageCount();
                        }
                    }else{
                        $('#cometchat_userslist').html('<div class="cometchat_nofriends">'+language[14]+'</div>');
                    }
                }
                if(jqcc.cometchat.getSessionVariable('buddylist')==1 || $('#cometchat_optionsbutton').hasClass('cometchat_tabclick')){
                    $("span.cometchat_userscontentavatar").find("img").each(function(){
                        if($(this).attr('original')){
                            $(this).attr("src", $(this).attr('original'));
                            $(this).removeAttr('original');
                        }
                    });
                }
                $("#cometchat_search").keyup();
                $('div.cometchat_userlist').unbind('click');
                $('div.cometchat_userlist').bind('click', function(e){
                    jqcc.cometchat.userClick(e.target);
                    jqcc.tapatalk.updateMessageCount();
                });
                //$('#cometchat_userstab_text').html(language[81]+' ('+(onlineNumber+jqcc.cometchat.getThemeVariable('jabberOnlineNumber'))+')');
                siteOnlineNumber = onlineNumber;
                jqcc.cometchat.setThemeVariable('lastOnlineNumber', onlineNumber+jqcc.cometchat.getThemeVariable('jabberOnlineNumber'));
                    $('#cometchat_searchbar').css('visibility', 'visible');
                    $('#cometchat_optionsbutton').removeClass('cometchat_noUser_optionBar');



            },
            loggedOut: function(){
                document.title = jqcc.cometchat.getThemeVariable('documentTitle');
                $("#loggedout").show();
                $("#cometchat_optionsbutton, #cometchat_userstab, #cometchat_chatboxes, #cometchat_chatbox_left, #cometchat_chatbox_right").hide();

                $("#cometchat_sidebar").css('display','none');
                $("#cometchat_userstab").css('display','none');

                $("span.cometchat_tabclick").removeClass("cometchat_tabclick");
                $("div.cometchat_tabopen").removeClass("cometchat_tabopen");
                jqcc.cometchat.setSessionVariable('openChatboxId', '');
                jqcc.cometchat.setThemeVariable('loggedout', 1);

            },
            userStatus: function(item){
                var cometchat_optionsbutton_popup = $('#cometchat_optionsbutton_popup');
                cometchat_optionsbutton_popup.find('textarea.cometchat_statustextarea').val(item.m);
                jqcc.cometchat.setThemeVariable('currentStatus', item.s);
                if(item.s=='offline'){
                    jqcc[settings.theme].goOffline(1);
                }else{
                    jqcc[settings.theme].removeUnderline();
                    $("#cometchat_userstab_icon").addClass('cometchat_user_'+item.s+'2');
                    cometchat_optionsbutton_popup.find("."+item.s).css('text-decoration', 'underline');
                }
                if(item.id>10000000){
                    $("#guestsname").show();
                     $("#guestsname").find("input.cometchat_guestnametextbox").val((item.n).replace("<?php echo $guestnamePrefix;?>-", ""));
                    cometchat_optionsbutton_popup.find("div.cometchat_tabsubtitle").html(language[45]);
                }
                jqcc.cometchat.setThemeVariable('userid', item.id);
                jqcc.cometchat.setThemeArray('buddylistStatus', item.id, item.s);
                jqcc.cometchat.setThemeArray('buddylistMessage', item.id, item.m);
                jqcc.cometchat.setThemeArray('buddylistName', item.id, item.n);
                jqcc.cometchat.setThemeArray('buddylistAvatar', item.id, item.a);
                jqcc.cometchat.setThemeArray('buddylistLink', item.id, item.l);

            },
            typingTo: function(item){
                $("span.cometchat_typing").css('display', 'none');
                var typingIds = item.split(',');
                var t = typingIds.length;
                while(t>-1){
                    $("#cometchat_typing_"+typingIds[t]).css('display', 'block');
                    t--;
                }
            },
           createChatboxData: function(id, name, status, message, avatar, link, silent, tryOldMessages){
                var cometchat_chatboxes = $("#cometchat_chatboxes");
                if(chatboxOpened[id]!=null){
                    if(!$("#cometchat_user_"+id).hasClass('cometchat_tabclick')&&silent!=1){
                        if(visibleTab.indexOf(id) == -1) {
                            $.tapatalk.swapTab(id);
                        } else {
                            $("#cometchat_user_"+id).click();
                        }
                    }
                    jqcc[settings.theme].scrollBars();
                    return;
                }

                var isMobile = '';
                if(jqcc.cometchat.getThemeArray('buddylistIsDevice', id) == 1) {
                     isMobile = '<div class="cometchat_isMobile cometchat_floatL"><div class="cometchat_mobileDot"></div></div>';
                }
                $('#cometchat_chatboxes_wide').width($('#cometchat_chatboxes_wide').width()+chatboxWidth+chatboxDistance);
                shortname = name;
                longname = name;
                $("<span/>").attr("id", "cometchat_user_"+id).attr("amount", 0).addClass("cometchat_tab").html(isMobile+'<div class="cometchat_userscontentdot101 cometchat_'+status+' cometchat_floatL"></div><div class="cometchat_user_shortname">'+shortname+'</div><div class="cometchat_unreadCount cometchat_floatL">0</div><div class="cometchat_closebox_bottom cometchat_tooltip" data-title="'+language[74]+'" id="cometchat_closebox_bottom_'+id+'">×</div>').appendTo($("#cometchat_chatboxes_wide"));
                var pluginshtml = '';
                var avchathtml = '';
                var smiliehtml = '';
                var pluginslength = settings.plugins.length;
                if(jqcc.cometchat.getThemeArray('isJabber', id)!=1){
                    if(pluginslength>0){
                        pluginshtml += '<div class="cometchat_plugins cometchat_floatR">';
                        for(var i = 0; i<pluginslength; i++){
                            var name = 'cc'+settings.plugins[i];
                            if(typeof ($[name])=='object'){
                                 if(settings.plugins[i]=='avchat') {
                                    avchathtml = '<div id="cometchat_'+settings.plugins[i]+'_'+id+'" class="cometchat_tooltip cometchat_pluginsicon cometchat_'+settings.plugins[i]+'" onclick="javascript:jqcc.'+name+'.init('+id+');" data-title="'+$[name].getTitle()+'"></div>';
                                } else if(settings.plugins[i]=='smilies') {
                                    smiliehtml = '<div id="cometchat_pluginscontainer"><div class="cometchat_pluginsicon cometchat_smilies" onclick="javascript:jqcc.ccsmilies.init('+id+');"></div><div class="cometchat_pluginsicon cometchat_filetransfer" onclick="javascript:jqcc.ccfiletransfer.init('+id+');"></div></div>';
                                } else if(settings.plugins[i]!='chattime'){
                                    pluginshtml += '<div class="cometchat_plugins_dropdownlist" onclick="javascript:jqcc.'+name+'.init('+id+');"><div class="cometchat_pluginsicon cometchat_'+settings.plugins[i]+' cometchat_floatL"></div><div class="cometchat_plugins_name cometchat_floatL">'+$[name].getTitle()+'</div></div>';
                                }
                            }
                        }
                        pluginshtml += '</div>';
                    }
                }
                var startlink = '';
                var endlink = '';
                if(link!=''){
                    startlink = '<a href="'+link+'">';
                    endlink = '</a>';
                }
                var blockPopup = '';
                var userisOffline = '<div class = "cometchat_userofflineMessage" id="cometchat_userofflineMessage_'+id+'">'+language[85]+'<span class = "closeOffline" id="close_offline_"'+id+'" onclick="jqcc.tapatalk.closeOffline('+id+')">x</span></div>';
                if(status != 'banned') {
                    var banNew = '<div id="cometchat_usercontrol"><span id="cometchat_ban_'+id+'" class="cc_ban" onclick="jqcc.tapatalk.blockinit('+id+')"></span><span id="cometchat_newlink" onclick="jqcc.tapatalk.startPopup('+id+')"></span></div>';

                    blockPopup = '<div id = "conf_block_'+id+'" class="conf_block"><div><p id="blockText">'+language[82]+'<label>'+language[83]+'<input type="checkbox" name="donotShowBlock" id="donotShowBlock" ></label></p><span class="conf_button_block">OK</span><span class="conf_button_block" style="right:75px;">'+language[80]+'</span></div></div>';
                }else {
                    var banNew = '<div id="cometchat_usercontrol"><span id="cometchat_ban_'+id+'" class="cc_unban" onclick="jqcc.tapatalk.unblockinit('+id+')">Unblock</span><span id="cometchat_newlink" onclick="jqcc.tapatalk.startPopup('+id+')"></span></div>';

                    blockPopup = '<div id = "conf_block_'+id+'" class="conf_block"><div><p id="blockText">'+language[84]+'<label>'+language[83]+'<input type="checkbox" name="donotShowBlock" id="donotShowBlock" ></label></p><span class="conf_button_block">OK</span><span class="conf_button_block" style="right:75px;">'+language[80]+'</span></div></div>';
                }

                var prepend = '<div class=\"cometchat_prependMessages\" onclick\="jqcc.tapatalk.prependMessagesInit('+id+')\" id = \"cometchat_prependMessages_'+id+'\">'+language[86]+'</div>';

                $("<div/>").attr("id", "cometchat_user_"+id+"_popup").addClass("cometchat_tabpopup").css('display', 'none').html('<div class="cometchat_tabtitle">'+isMobile+'<div class="cometchat_userscontentdot101 cometchat_'+status+' cometchat_floatL" style="top:5px;"></div><div class="cometchat_name">'+startlink+longname+endlink+'<span id="cometchat_typing_'+id+'" class="cometchat_typing"></span></div><div id="cometchat_closebox_'+id+'" data-title="'+language[74]+'" class="cometchat_closebox cometchat_floatR cometchat_tooltip">×</div><div class="cometchat_plugins_dropdown cometchat_floatR"><div class="cometchat_plugins_dropdown_icon cometchat_tooltip" id="cometchat_plugins_dropdown_icon_'+id+'" data-title="'+language[73]+'"></div><div class="cometchat_popup_plugins">'+pluginshtml+'</div></div>'+avchathtml+'</div><div class="cometchat_tabsubtitle cometchat_tabcontent"><div class="cometchat_message">'+banNew+'</div>'+blockPopup+'<div class="cometchat_tabcontenttext" id="cometchat_tabcontenttext_'+id+'">'+userisOffline+prepend+'</div><div class="cometchat_tabcontentinput"><textarea class="cometchat_textarea" placeholder="'+language[55]+'"></textarea>'+smiliehtml+'</div><div style="clear:both"></div></div>').appendTo($("#cometchat"));
                var cometchat_user_popup = $("#cometchat_user_"+id+'_popup');

                cometchat_user_popup.find('div.cometchat_plugins_dropdown').hide();
                if(status != 'offline'){
                    cometchat_user_popup.find('div#cometchat_userofflineMessage_'+id).hide();
                }
                jqcc.cometchat.setThemeArray('chatBoxesOrder', id, 0);
                chatboxOpened[id] = 0;
                allChatboxes[id] = 0;
                jqcc.cometchat.orderChatboxes();
                jqcc[settings.theme].windowResize(1);
                var cometchat_user_id = $("#cometchat_user_"+id);
                cometchat_user_id.mouseenter(function(){
                    $(this).addClass("cometchat_tabmouseover");
                    cometchat_user_id.find("div.cometchat_user_shortname").addClass("cometchat_tabmouseovertext");
                });
                cometchat_user_id.mouseleave(function(){
                    $(this).removeClass("cometchat_tabmouseover");
                    cometchat_user_id.find("div.cometchat_user_shortname").removeClass("cometchat_tabmouseovertext");
                });
                cometchat_user_id.click(function(){

                    if($(this).hasClass('cometchat_tabclick')){
                        $(this).removeClass("cometchat_tabclick").removeClass("cometchat_usertabclick");
                        cometchat_user_popup.removeClass("cometchat_tabopen");
                        cometchat_user_id.find("div.cometchat_closebox_bottom").removeClass("cometchat_closebox_bottom_click");
                        jqcc.cometchat.setThemeArray('chatBoxesOrder', id, 0);
                        chatboxOpened[id] = 0;
                        allChatboxes[id] = 0;

                        removeOpenChatboxId(id);
                    }else{
                        if((cometchat_user_id.offset().left<(cometchat_chatboxes.offset().left+cometchat_chatboxes.width()))&&(cometchat_user_id.offset().left-cometchat_chatboxes.offset().left)>=0){
                            $(this).addClass("cometchat_tabclick").addClass("cometchat_usertabclick");
                            cometchat_user_popup.addClass("cometchat_tabopen").addClass('cometchat_tabopen_bottom');
                            cometchat_user_id.find("div.cometchat_closebox_bottom").addClass("cometchat_closebox_bottom_click");
                            chatboxOpened[id] = 1;
                            addOpenChatboxId(id);
                            if(olddata[id]!=1||(isNaN(id))){
                                $("#cometchat_tabcontenttext_"+id).find('div.cometchat_chatboxmessage').remove();
                                jqcc[settings.theme].updateChatbox(id);
                                olddata[id] = 1;
                            }
                        }else{
                            cometchat_user_id.removeClass('cometchat_tabclick').removeClass("cometchat_usertabclick");
                            chatboxOpened[id] = 1;
                            addOpenChatboxId(id);
                        }
                        allChatboxes[id] = 1;
                        jqcc[settings.theme].scrollDown(id);
                    }
                    jqcc.cometchat.setThemeArray('chatBoxesOrder', id, 0);
                    jqcc.cometchat.orderChatboxes();
                    jqcc.tapatalk.updateMessageCount();
                    if(jqcc.cometchat.getInternalVariable('updatingsession')!=1){
                        cometchat_user_popup.find("textarea.cometchat_textarea").focus();
                    }
                    $(this).find('div.cometchat_unreadCount').html('0').hide();
                    $.tapatalk.rearrange();
                });
                cometchat_user_id.find("div.cometchat_closebox_bottom").mouseenter(function(){
                    $(this).addClass("cometchat_closebox_bottomhover");
                });
                cometchat_user_id.find("div.cometchat_closebox_bottom").mouseleave(function(){
                    $(this).removeClass("cometchat_closebox_bottomhover");
                });
                cometchat_user_id.find("div.cometchat_closebox_bottom").click(function(){
                    cometchat_user_popup.remove();
                    $("#cometchat_user_"+id).remove();
                    $('#cometchat_chatboxes_wide').width($('#cometchat_chatboxes_wide').width()-160-chatboxDistance);
                    jqcc.cometchat.setThemeArray('chatBoxesOrder', id, null);
                    delete(chatboxOpened[id]);
                    delete(allChatboxes[id]);
                    olddata[id] = 0;
                    jqcc.cometchat.orderChatboxes();
                    removeOpenChatboxId(id);
                    jqcc[settings.theme].windowResize();
                });
                if(jqcc().slimScroll){
                    $("#cometchat_tabcontenttext_"+id).slimScroll({height: (chatboxHeight+10)+'px',railAlwaysVisible: true});
                    var currElem = cometchat_user_popup.find('div.cometchat_plugins_dropdownlist');
                    var maxPlugin = (currElem.length>8)?8:currElem.length;
                    var scrollHeight = (maxPlugin * currElem.outerHeight())+4+'px';
                    cometchat_user_popup.find('div.cometchat_plugins').slimScroll({'width':'100%','height':scrollHeight});
                }
                chatbottom[id] = 1;
                cometchat_user_popup.find("div.cometchat_tabcontenttext").scroll(function(){
                    if(cometchat_user_popup.find("div.cometchat_tabcontenttext").height()<(cometchat_user_popup.find("div.slimScrollBar").height()+cometchat_user_popup.find("div.slimScrollBar").position().top)){
                        chatbottom[id] = 1;
                        $('#cometchat_tabcontenttext_'+id).find('.cometchat_new_message_unread').remove();
                    }else{
                        chatbottom[id] = 0;
                    }
                });
                var cometchat_textarea = $("#cometchat_user_"+id+'_popup').find("textarea.cometchat_textarea");
                cometchat_textarea.keydown(function(event){
                    return jqcc[settings.theme].chatboxKeydown(event, this, id);
                });
                cometchat_textarea.keyup(function(event){
                    return jqcc[settings.theme].chatboxKeyup(event, this, id);
                });
                cometchat_textarea.focus(function(){
                    clearInterval(blinkInterval);
                    cometchat_user_popup.find('div.cometchat_new_message_titlebar').removeClass('cometchat_new_message_titlebar');
                });
                var cometchat_tabtitle = cometchat_user_popup.find("div.cometchat_tabtitle");
                cometchat_tabtitle.find("div.cometchat_closebox").mouseenter(function(){
                    $(this).addClass("cometchat_chatboxmouseoverclose");
                });
                cometchat_tabtitle.find("div.cometchat_closebox").mouseleave(function(){
                    $(this).removeClass("cometchat_chatboxmouseoverclose");
                });
                cometchat_tabtitle.find(".cometchat_closebox").click(function(){
                    cometchat_user_id.find("div.cometchat_closebox_bottom").click();
                });
                cometchat_tabtitle.click(function(){
                    $(this).removeClass('cometchat_fullOpacity').find('div.cometchat_popup_plugins').slideUp('fast');
                    cometchat_user_id.click();
                });
                $('div.cometchat_plugins_dropdown','#cometchat_user_'+id+'_popup').click(function(e){
                    e.stopImmediatePropagation();
                    $(this).toggleClass('cometchat_plugins_dropdown_active').find('div.cometchat_popup_plugins').slideToggle('fast');
                    cometchat_user_popup.find('div.cometchat_tabtitle').toggleClass('cometchat_fullOpacity');
                });
                $('div.cometchat_pluginsicon','#cometchat_user_'+id+'_popup').click(function(e){
                    e.stopImmediatePropagation();
                    $(this).parents('div.cometchat_popup_plugins').slideUp('fast');
                    cometchat_user_popup.find('div.cometchat_tabtitle').removeClass('cometchat_fullOpacity');
                });
                if(silent!=1){
                    if(visibleTab.indexOf(id) == -1) {
                        $.tapatalk.swapTab(id);
                    } else {
                        cometchat_user_id.click();
                    }
                }
                $('div.cometchat_tabcontenttext').click(function(e){
                    e.stopImmediatePropagation();
                    if($('div.cometchat_popup_plugins').css('display').localeCompare('block') === 0){
                        $('div.cometchat_plugins_dropdown').toggleClass('cometchat_plugins_dropdown_active').find('div.cometchat_popup_plugins').slideToggle('fast');
                        cometchat_user_popup.find('div.cometchat_tabtitle').toggleClass('cometchat_fullOpacity');
                    }
                });
            },
            addMessages: function(item){
                $.each(item, function(i, incoming){

                    if(typeof(incoming.self) ==='undefined' && typeof(incoming.old) ==='undefined' && typeof(incoming.sent) ==='undefined'){
                        incoming.sent = Math.floor(new Date().getTime()/1000);
                        incoming.old = incoming.self = 1;
                    }
                    if(typeof(incoming.m)!== 'undefined'){
                        incoming.message = incoming.m;
                    }
                    var processedmessage = incoming.message;
                    if((incoming.message).indexOf('CC^CONTROL_')!=-1){
                        processedmessage = jqcc.cometchat.processcontrolmessage(incoming);
                    }
                    if(processedmessage == null){
                        return;
                    }
                    checkfirstmessage = ($("#cometchat_tabcontenttext_"+incoming.from+" .cometchat_chatboxmessage").length) ? 0 : 1;
                    var chatboxopen = 0;
                    var shouldPop = 0;
                    if($('#cometchat_user_'+incoming.from).length == 0){
                        shouldPop = 1;
                    }
                    if(jqcc.cometchat.getThemeArray('trying', incoming.from)===undefined){
                        if(typeof (jqcc[settings.theme].createChatbox)!=='undefined'){
                            jqcc[settings.theme].createChatbox(incoming.from, jqcc.cometchat.getThemeArray('buddylistName', incoming.from), jqcc.cometchat.getThemeArray('buddylistStatus', incoming.from), jqcc.cometchat.getThemeArray('buddylistMessage', incoming.from), jqcc.cometchat.getThemeArray('buddylistAvatar', incoming.from), jqcc.cometchat.getThemeArray('buddylistLink', incoming.from), 1, 1);
                            chatboxopen = 0;
                        }
                    }
                    if(chatboxOpened[incoming.from]!=1&&incoming.old!=1){
                        if (incoming.self != 1 && settings.messageBeep == 1) {
                            if ($.cookie(settings.cookiePrefix + "sound") && $.cookie(settings.cookiePrefix + "sound") == 'true') {
                            } else {
                                jqcc[settings.theme].playSound();
                            }
                        }
                        jqcc[settings.theme].addPopup(incoming.from, 1, 1);
                    }
                    if(incoming.self!=1&&settings.messageBeep==1){
                        if($.cookie(settings.cookiePrefix+"sound")&&$.cookie(settings.cookiePrefix+"sound")=='true'){
                        }else{
                            if(incoming.old!=1){
                                jqcc[settings.theme].playSound();
                            }
                        }
                    }
                    if(jqcc.cometchat.getThemeArray('buddylistName', incoming.from)==null||jqcc.cometchat.getThemeArray('buddylistName', incoming.from)==''){
                        if(jqcc.cometchat.getThemeArray('trying', incoming.from)<5){
                            setTimeout(function(){
                                if(typeof (jqcc[settings.theme].addMessages)!=='undefined'){
                                    jqcc[settings.theme].addMessages([{"from": incoming.from, "message": incoming.message, "self": incoming.self, "old": incoming.old, "id": incoming.id, "sent": incoming.sent}]);
                                }
                            }, 2000);
                        }
                    }else{
                        var selfstyleAvatar = "";
                        var avatar = baseUrl+"themes/tapatalk/images/noavatar.png";
                        if(parseInt(incoming.self)==1){
                            fromname = language[10];
                        }else{
                            fromname = jqcc.cometchat.getThemeArray('buddylistName', incoming.from);
                            if(jqcc.cometchat.getThemeArray('buddylistAvatar', incoming.from)!=""){
                                avatar = jqcc.cometchat.getThemeArray('buddylistAvatar', incoming.from);
                            }
                            selfstyleAvatar = '<a class="cometchat_floatL" href="'+jqcc.cometchat.getThemeArray('buddylistLink', incoming.from)+'"><img src="'+avatar+'" title="'+fromname+'"/></a>';
                        }
                        if($("#cometchat_message_"+incoming.id).length>0){
                            $("#cometchat_message_"+incoming.id).find('div.cometchat_chatboxmessagecontent').html(incoming.message);
                        }else{
                            sentdata = '';
                            if(incoming.sent!=null){
                                var ts = incoming.sent;
                                sentdata = jqcc[settings.theme].getTimeDisplay(ts);
                            }
                            if(!settings.fullName){
                                fromname = fromname.split(" ")[0];
                            }
                            var msg = '';
                            var addMessage = 0;
                            var avatar = baseUrl+"themes/tapatalk/images/noavatar.png";
                            if(parseInt(incoming.self)==1){
                                msg = '<div class="cometchat_chatboxmessage" id="cometchat_message_'+incoming.id+'"><div class="cometchat_chatboxmessagecontent cometchat_self cometchat_floatR" title="'+sentdata+'">'+processedmessage+'</div><div class="selfMsgArrow"><div class="after"></div></div>';
                                addMessage = 1;

                            }else{
                                msg = '<div class="cometchat_chatboxmessage" id="cometchat_message_'+incoming.id+'">'+selfstyleAvatar+'<div class="cometchat_chatboxmessagecontent cometchat_floatL" title="'+sentdata+'">'+processedmessage+'</div><div class="msgArrow"><div class="after"></div></div></div>';
                                addMessage = 1;
                            }
                            if(addMessage==1&&chatboxopen==0){
                                $("#cometchat_tabcontenttext_"+incoming.from).append(msg);
                            }
                            $("#cometchat_typing_"+incoming.from).css('display', 'none');
                            jqcc[settings.theme].scrollDown(incoming.from);
                                                            var nowTime = new Date();
                                                            var idleDifference = Math.floor(nowTime.getTime()/1000)-jqcc.cometchat.getThemeVariable('idleTime');
                                                            if(idleDifference>5){
                                                                    if(settings.windowTitleNotify==1){
                                                                            document.title = language[15];
                                                                    }
                                                            }
                                                    }
                                            }
                    var newMessage = 0;
                    var isActiveChatBox = $('#cometchat_user_'+incoming.from+'_popup').find('textarea.cometchat_textarea').is(':focus');
                    if((jqcc.cometchat.getThemeVariable('isMini')==1||!isActiveChatBox)&&incoming.self!=1&&incoming.old==0&&settings.desktopNotifications==1){
                        var callChatboxEvent = function(){
                            if(typeof incoming.from!='undefined'){
                                for(x in desktopNotifications){
                                    for(y in desktopNotifications[x]){
                                        desktopNotifications[x][y].close();
                                    }
                                }
                                desktopNotifications = {};
                                if(jqcc.cometchat.getThemeVariable('isMini')==1){
                                    window.focus();
                                }
                                jqcc.cometchat.chatWith(incoming.from);
                            }
                        };
                        if(typeof desktopNotifications[incoming.from]!='undefined'){
                            var newMessageCount = 0;
                            for(x in desktopNotifications[incoming.from]){
                                ++newMessageCount;
                                desktopNotifications[incoming.from][x].close();
                            }
                            jqcc.cometchat.notify((++newMessageCount)+' '+language[46]+' '+jqcc.cometchat.getThemeArray('buddylistName', incoming.from), jqcc.cometchat.getThemeArray('buddylistName', incoming.from), language[47], callChatboxEvent, incoming.from, incoming.id);
                        }else{
                            jqcc.cometchat.notify(language[48]+' '+jqcc.cometchat.getThemeArray('buddylistName', incoming.from), jqcc.cometchat.getThemeArray('buddylistAvatar', incoming.from), processedmessage, callChatboxEvent, incoming.from, incoming.id);
                        }
                    }
                    var chatBoxArray = jqcc.cometchat.getThemeVariable('openChatboxId');
                    if($.inArray(incoming.from + '',chatBoxArray)==-1&&settings.autoPopupChatbox==1&&shouldPop==1&&incoming.self==0){
                        jqcc.cometchat.tryClick(incoming.from);
                    }
                    var totalHeight = 0;
                    $("#cometchat_tabcontenttext_"+incoming.from).children().each(function(){
                        totalHeight = totalHeight+$(this).outerHeight();
                    });
                    if(newMessage>0){
                        if($('#cometchat_tabcontenttext_'+incoming.from).outerHeight()<totalHeight){
                            $('#cometchat_tabcontenttext_'+incoming.from).append('<div class="cometchat_new_message_unread"><a herf="javascript:void(0)" onClick="javascript:jqcc.tapatalk.scrollDown('+incoming.from+');jqcc(\'#cometchat_tabcontenttext_'+incoming.from+' .cometchat_new_message_unread\').remove();">&#9660 '+language[54]+'</a></div>');
                        }
                    }
                    if(incoming.old==0&&!isActiveChatBox){
                        $.tapatalk.blinkPopupTitle(incoming.from);
                    }
                    if(visibleTab.indexOf(incoming.from) == -1) {
                        var unreadUnseenCount = $('#cometchat_unseenUsers').find('.unread_msg').length;
                        if(unreadUnseenCount > 0) {
                            $('#cometchat_unseenUserCount').html(unreadUnseenCount).show();
                        } else {
                            $('#cometchat_unseenUserCount').hide();
                        }
                    }
                });
                var chatBoxesSeen = [];

                $.each(item, function(i, incoming){
                    if($.inArray(incoming.from,chatBoxesSeen) === -1){
                        $("#cometchat_tabcontenttext_"+incoming.from).find('.delivery_message').remove();
                        $("#cometchat_tabcontenttext_"+incoming.from).find('.delivery_message_delivering').remove();
                        $("#cometchat_tabcontenttext_"+incoming.from).find('.cometchat_self').removeClass('deliverMsg_'+incoming.from);
                        var LastMessage = $("#cometchat_tabcontenttext_"+incoming.from+" .cometchat_chatboxmessage:last");
                        var msg = LastMessage.text();
                        if(LastMessage.has('.cometchat_self').length){
                            if(jqcc.cometchat.getThemeArray('buddylistStatus',incoming.from) == "offline"){
                                var LastSelfMessage = $("#cometchat_tabcontenttext_"+incoming.from+" .cometchat_chatboxmessage .cometchat_self:last");
                                LastSelfMessage.addClass('deliverMsg_'+incoming.from);
                                if(typeof document.getElementsByClassName("deliverMsg_"+incoming.from)[0] !== 'undefined'){
                                    document.getElementsByClassName("deliverMsg_"+incoming.from)[0].parentNode.innerHTML += '<div class="delivery_message_delivering">Delivering</div>';
                                }
                            }
                            else
                            {
                                var LastSelfMessage = $("#cometchat_tabcontenttext_"+incoming.from+" .cometchat_chatboxmessage .cometchat_self:last");
                                LastSelfMessage.addClass('deliverMsg_'+incoming.from);
                                if(typeof document.getElementsByClassName("deliverMsg_"+incoming.from)[0] !== 'undefined'){
                                    document.getElementsByClassName("deliverMsg_"+incoming.from)[0].parentNode.innerHTML += '<div class="delivery_message">Delivered</div>';
                                }
                            }

                        } else{
                            var LastSelfMessage = $("#cometchat_tabcontenttext_"+incoming.from+" .cometchat_chatboxmessage .cometchat_self:last");
                            LastSelfMessage.addClass('deliverMsg_'+incoming.from);
                            if(typeof document.getElementsByClassName("deliverMsg_"+incoming.from)[0] !== 'undefined'){
                                 document.getElementsByClassName("deliverMsg_"+incoming.from)[0].parentNode.innerHTML += '<div class="delivery_message">Delivered</div>';
                            }

                        }
                        chatBoxesSeen.push(incoming.from);
                    }

                });
            },
            statusSendMessage: function(statustextarea){
                var message = $("#cometchat_optionsbutton_popup").find("textarea.cometchat_statustextarea").val();
                var oldMessage = jqcc.cometchat.getThemeArray('buddylistMessage', jqcc.cometchat.getThemeVariable('userid'));
                if(message!=''&&oldMessage!=message){
                    $('div.cometchat_statusbutton').html('<img src="'+baseUrl+'themes/tapatalk/images/loader.gif" width="16">');
                    jqcc.cometchat.setThemeArray('buddylistMessage', jqcc.cometchat.getThemeVariable('userid'), message);
                    jqcc.cometchat.statusSendMessageSet(message, statustextarea);
                }else{
                    $('div.cometchat_statusbutton').text('<?php echo $language[57]; ?>');
                    setTimeout(function(){
                        $('div.cometchat_statusbutton').text('<?php echo $language[22]; ?>');
                    }, 1500);
                }
            },
            statusSendMessageSuccess: function(statustextarea){
                $(statustextarea).blur();
                setTimeout(function(){
                    $('div.cometchat_statusbutton').text('<?php echo $language[49]; ?>');
                }, 1800);
                setTimeout(function(){
                    $('div.cometchat_statusbutton').text('<?php echo $language[22]; ?>');
                }, 2500);
            },
            statusSendMessageError: function(){
                setTimeout(function(){
                    $('div.cometchat_statusbutton').text('<?php echo $language[50]; ?>');
                }, 1800);
                setTimeout(function(){
                    $('div.cometchat_statusbutton').text('<?php echo $language[22]; ?>');
                }, 2500);
            },
            setGuestName: function(guestnametextarea){
                var guestname = $("#cometchat_optionsbutton_popup").find("input.cometchat_guestnametextbox").val();
                var oldguestname = jqcc.cometchat.getThemeArray('buddylistName', jqcc.cometchat.getThemeVariable('userid'));
                if(guestname!=''&&oldguestname!=guestname){
                    $('div.cometchat_guestnamebutton').html('<img src="'+baseUrl+'"themes/tapatalk/images/loader.gif" width="16">');
                    jqcc.cometchat.setThemeArray('buddylistName', jqcc.cometchat.getThemeVariable('userid'), guestname);
                    jqcc.cometchat.setGuestNameSet(guestname, guestnametextarea);
                }else{
                    $('div.cometchat_guestnamebutton').text('<?php echo $language[57]; ?>');
                    setTimeout(function(){
                        $('div.cometchat_guestnamebutton').text('<?php echo $language[44]; ?>');
                    }, 1500);
                }
            },
            setGuestNameSuccess: function(guestnametextarea){
                $(guestnametextarea).blur();
                setTimeout(function(){
                    $('div.cometchat_guestnamebutton').text('<?php echo $language[49]; ?>');
                }, 1800);
                setTimeout(function(){
                    $('div.cometchat_guestnamebutton').text('<?php echo $language[44]; ?>');
                }, 2500);
            },
            setGuestNameError: function(){
                setTimeout(function(){
                    $('div.cometchat_guestnamebutton').text('<?php echo $language[50]; ?>');
                }, 1800);
                setTimeout(function(){
                    $('div.cometchat_guestnamebutton').text('<?php echo $language[44]; ?>');
                }, 2500);
            },
            removeUnderline: function(){
                $("#cometchat_optionsbutton_popup").find("span.busy").css('text-decoration', 'none');
                $("#cometchat_optionsbutton_popup").find("span.invisible").css('text-decoration', 'none');
                $("#cometchat_optionsbutton_popup").find("span.available").css('text-decoration', 'none');
                jqcc[settings.theme].removeUnderline2();
            },
            removeUnderline2: function(){
                $("#cometchat_userstab_icon").removeClass('cometchat_user_available2');
                $("#cometchat_userstab_icon").removeClass('cometchat_user_busy2');
                $("#cometchat_userstab_icon").removeClass('cometchat_user_invisible2');
                $("#cometchat_userstab_icon").removeClass('cometchat_user_away2');
            },
            goOffline: function(silent){
                jqcc.cometchat.setThemeVariable('offline', 1);
                jqcc[settings.theme].removeUnderline();
                $("#cometchat_userstab_icon").addClass('cometchat_user_invisible2');
                if(silent!=1){
                    jqcc.cometchat.sendStatus('offline');
                }
                $('#cometchat_userstab').removeClass('cometchat_userstabclick cometchat_tabclick');
                $('div.cometchat_tabopen').removeClass('cometchat_tabopen');
                $('span.cometchat_tabclick').removeClass('cometchat_tabclick');
                $('#cometchat_sidebar').hide();
                $('#cometchat_userstab_popup').show();
                $('#cometchat_optionsbutton_popup').hide();
                jqcc.cometchat.setSessionVariable('buddylist', '0');
                $('#cometchat_userstab_text').html(language[17]);
                for(var chatbox in jqcc.cometchat.getThemeVariable('chatBoxesOrder')){
                    if(jqcc.cometchat.getThemeVariable('chatBoxesOrder').hasOwnProperty(chatbox)){
                        if(jqcc.cometchat.getThemeVariable('chatBoxesOrder')[chatbox]!==null){
                            $("#cometchat_user_"+chatbox).find(".cometchat_closebox_bottom").click();
                        }
                    }
                }
                $('.cometchat_container').remove();
                if(typeof window.cometuncall_function=='function'){
                    cometuncall_function(cometid);
                }
                jqcc.cometchat.setSessionVariable('openChatboxId', '');
            },
            tryAddMessages: function(id, atleastOneNewMessage){
                if(jqcc.cometchat.getThemeArray('buddylistName', id)==null||jqcc.cometchat.getThemeArray('buddylistName', id)==''){
                    if(jqcc.cometchat.getThemeArray('trying', id)<5){
                        setTimeout(function(){
                            if(typeof (jqcc[settings.theme].tryAddMessages)!=='undefined'){
                                jqcc[settings.theme].tryAddMessages(id, atleastOneNewMessage);
                            }
                        }, 1000);
                    }
                }else{
                    $("#cometchat_typing_"+id).css('display', 'none');
                    if(resynch<1){
                        jqcc[settings.theme].scrollDown(id);
                        resynch = 2;
                    }
                    if(chatbottom[id]==1){
                        $('#cometchat_tabcontenttext_'+id).find(".cometchat_new_message_unread").remove();
                        jqcc[settings.theme].scrollDown(id);
                    }else{
                        $('#cometchat_tabcontenttext_'+id).find(".cometchat_new_message_unread").show();
                    }
                    chatboxOpened[id] = 1;
                    if(atleastOneNewMessage==1){
                        var nowTime = new Date();
                        var idleDifference = Math.floor(nowTime.getTime()/1000)-jqcc.cometchat.getThemeVariable('idleTime');
                        if(idleDifference>5){
                            document.title = language[15];
                        }
                    }
                    if($.cookie(settings.cookiePrefix+"sound")&&$.cookie(settings.cookiePrefix+"sound")=='true'){
                    }else{
                        if(atleastOneNewMessage==1){
                            jqcc[settings.theme].playSound();
                        }
                    }
                }
            },
            countMessage: function(){
                if(jqcc.cometchat.getThemeVariable('loggedout')==0){
                    var cc_state = $.cookie(settings.cookiePrefix+'state');
                    jqcc.cometchat.setInternalVariable('updatingsession', '1');
                    if(cc_state!=null){
                        var cc_states = cc_state.split(/:/);
                        if(jqcc.cometchat.getThemeVariable('offline')==0){
                            var value = 0;
                            if(cc_states[0]!=' '&&cc_states[0]!=''){
                                value = cc_states[0];
                            }
                            if((value==0&&$('#cometchat_userstab').hasClass("cometchat_tabclick"))||(value==1&&!($('#cometchat_userstab').hasClass("cometchat_tabclick")))){
                                $('#cometchat_userstab').click();
                            }
                            value = '';
                            if(cc_states[1]!=' '&&cc_states[1]!=''){
                                value = cc_states[1];
                            }
                            if(value==jqcc.cometchat.getSessionVariable('activeChatboxes')){
                                var newActiveChatboxes = {};
                                if(value!=''){
                                    var badge = 0;
                                    var chatboxData = value.split(/,/);
                                    for(i = 0; i<chatboxData.length; i++){
                                        var chatboxIds = chatboxData[i].split(/\|/);
                                        newActiveChatboxes[chatboxIds[0]] = chatboxIds[1];
                                        badge += parseInt(chatboxIds[1]);
                                    }
                                    favicon.badge(badge);
                                }
                            }
                        }
                    }
                }
            },
            resynch: function(){
                if(jqcc.cometchat.getThemeVariable('loggedout')==0){
                    if(resynch<0){
                        resynch = 1;
                    }
                    var cc_state = $.cookie(settings.cookiePrefix+'state');
                    jqcc.cometchat.setInternalVariable('updatingsession', '1');
                    if(cc_state!=null){
                        var cc_states = cc_state.split(/:/);
                        if(jqcc.cometchat.getThemeVariable('offline')==0){
                            var value = 0;
                            if(cc_states[0]!=' '&&cc_states[0]!=''){
                                value = cc_states[0];
                            }
                            if((value==0&&$('#cometchat_userstab').hasClass("cometchat_tabclick"))||(value==1&&!($('#cometchat_userstab').hasClass("cometchat_tabclick")))){
                                $('#cometchat_userstab').click();
                            }
                            value = '';
                            if(cc_states[1]!=' '&&cc_states[1]!=''){
                                value = cc_states[1];
                            }
                            if(value!=jqcc.cometchat.getSessionVariable('activeChatboxes')){
                                var newActiveChatboxes = {};
                                var oldActiveChatboxes = {};
                                if(value!=''){
                                    var chatboxData = value.split(/,/);
                                    var count = 0;
                                    for(i = 0; i<chatboxData.length; i++){
                                        var chatboxIds = chatboxData[i].split(/\|/);
                                        newActiveChatboxes[chatboxIds[0]] = chatboxIds[1];
                                        count += parseInt(chatboxIds[1]);
                                    }
                                    if(settings.windowFavicon==1){
                                        favicon.badge(count);
                                    }
                                }
                                if(jqcc.cometchat.getSessionVariable('activeChatboxes')!=''){
                                    var chatboxData = jqcc.cometchat.getSessionVariable('activeChatboxes').split(/,/);
                                    for(i = 0; i<chatboxData.length; i++){
                                        var chatboxIds = chatboxData[i].split(/\|/);
                                        oldActiveChatboxes[chatboxIds[0]] = chatboxIds[1];
                                    }
                                }
                                for(r in newActiveChatboxes){
                                    if(newActiveChatboxes.hasOwnProperty(r)){
                                        jqcc[settings.theme].addPopup(r, parseInt(newActiveChatboxes[r]), 0);
                                        if(parseInt(newActiveChatboxes[r])>0){
                                            jqcc.cometchat.setThemeVariable('newMessages', 1);
                                        }
                                    }
                                }
                                allChatboxes = newActiveChatboxes;
                                for(y in oldActiveChatboxes){
                                    if(oldActiveChatboxes.hasOwnProperty(y)){
                                        if(newActiveChatboxes[y]==null){
                                            $("#cometchat_user_"+y+"_popup").find("div.cometchat_closebox").click();
                                        }
                                    }
                                }
                            }
                            if(jqcc.cometchat.getThemeVariable('newMessages')>0){
                                if(settings.windowFavicon==1){
                                    jqcc[settings.theme].countMessage();
                                }
                                if(document.title==language[15]){
                                    document.title = jqcc.cometchat.getThemeVariable('documentTitle');
                                }else{
                                    if(settings.windowTitleNotify==1){
                                        document.title = language[15];
                                    }
                                }
                            }else{
                                var nowTime = new Date();
                                var idleDifference = Math.floor(nowTime.getTime()/1000)-jqcc.cometchat.getThemeVariable('idleTime');
                                if(idleDifference<5){
                                    document.title = jqcc.cometchat.getThemeVariable('documentTitle');
                                    if(settings.windowFavicon==1){
                                        favicon.badge(0);
                                    }
                                }
                            }
                            value = 0;
                            if(cc_states[2]!=' '&&cc_states[2]!=''){
                                value = cc_states[2];
                                jqcc.cometchat.setSessionVariable('openChatboxId', value);
                                if(reload==0){
                                    reload = 1;
                                    var temp = value.split(",");
                                    jqcc.cometchat.setThemeVariable('openChatboxId', temp);
                                    for(i = 0; i<temp.length; i++){
                                        if(!$("#cometchat_user_"+temp[i]+"_popup").hasClass('cometchat_tabopen')){
                                            $("#cometchat_user_"+temp[i]).click();
                                        }
                                    }
                                }
                            }
                            if(cc_states[4]==1){
                                jqcc[settings.theme].goOffline(1);
                            }
                        }
                        if(cc_states[4]==0&&jqcc.cometchat.getThemeVariable('offline')==1){
                            jqcc.cometchat.setThemeVariable('offline', 0);
                            //$('#cometchat_userstab_text').html(language[81]);
                            $('#cometchat_userstab_text').html(language[81]+'('+jqcc.tapatalk.updateMessageCount(1)+')');
                            jqcc.cometchat.chatHeartbeat(1);
                            jqcc[settings.theme].removeUnderline();
                            $("#cometchat_userstab_icon").addClass('cometchat_user_available2');
                            $("#cometchat_optionsbutton_popup").find("span.available").css('text-decoration', 'underline');
                        }
                    }
                    jqcc.cometchat.setInternalVariable('updatingsession', '0');
                    clearTimeout(resynchTimer);
                    resynchTimer = setTimeout(function(){
                        jqcc[settings.theme].resynch();
                    }, 5000);
                }
            },
            setModuleAlert: function(id, number){
                if($("#cometchat_trayicon_"+id).find("span.cometchat_tabalert").length > 0){
                    $("#cometchat_trayicon_"+id).find("span.cometchat_tabalert").remove();
                }
                if(number!=0){
                    $("<span>").addClass("cometchat_tabalert").html(number).appendTo($("#cometchat_trayicon_"+id));
                }
            },
            addPopup: function(id, amount, add){
                console.log('addPopup');
                if(jqcc.cometchat.getThemeArray('buddylistName', id)==null||jqcc.cometchat.getThemeArray('buddylistName', id)==''){
                    if(jqcc.cometchat.getThemeArray('trying', id)===undefined){
                        jqcc[settings.theme].createChatbox(id, null, null, null, null, null, 1, null);
                    }
                    if(jqcc.cometchat.getThemeArray('trying', id)<5){
                        setTimeout(function(){
                            jqcc[settings.theme].addPopup(id, amount, add);
                        }, 5000);
                    }
                }else{
                    jqcc.cometchat.userDoubleClick(id);
                    amount = parseInt($("#cometchat_user_"+id).attr('amount'))+parseInt(amount);
                    var cometchat_user_id = $("#cometchat_user_"+id);
                    if(amount==0){
                        cometchat_user_id.removeClass('cometchat_new_message').attr('amount', 0).find('div.cometchat_unreadCount').html(amount).hide();
                        $("#cometchat_userlist_"+id).find('span.cometchat_usersnewmessages').hide();
                        $("#unseenUser_"+id).removeClass('unread_msg').find('div.cometchat_unreadCount').html(amount).removeClass('cometchat_unseenUsers_unread');
                    }else{
                        cometchat_user_id.addClass('cometchat_new_message').attr('amount', amount).find('div.cometchat_unreadCount').html('('+amount+')').show();
                        $("#cometchat_userlist_"+id).find('span.cometchat_usersnewmessages').text(amount).show();
                        $("#unseenUser_"+id).addClass('unread_msg').find('div.cometchat_unreadCount').html('('+amount+')').addClass('cometchat_unseenUsers_unread');

                        if(flick == -1)
                        flick = jqcc.tapatalk.flicker();
                    }

                    cometchat_user_id.click(function(){
                        jqcc.tapatalk.updateMessageCount();
                        cometchat_user_id.removeClass('cometchat_new_message').attr('amount', 0);
                        jqcc.cometchat.setThemeVariable('newMessages', 0);
                        clearInterval(flick);
                        jqcc('#cometchat_userstab').css('background',bg1);
                        $("#cometchat_userlist_"+id).find('span.cometchat_usersnewmessages').text(amount).hide();
                        flick = -1;
                    });


                    jqcc.cometchat.setThemeArray('chatBoxesOrder', id, amount);
                    jqcc.cometchat.orderChatboxes();
                    jqcc.tapatalk.updateMessageCount();
                }
                if(settings.showSettingsTab==1&&settings.showOnlineTab==0){
                    $("#cometchat_chatboxes_wide span").click(function(){
                        if($('#cometchat_optionsbutton').hasClass('cometchat_tabclick')){
                            $('#cometchat_optionsbutton').removeClass('cometchat_tabclick').removeClass('cometchat_usertabclick');
                            $('#cometchat_optionsbutton_popup').removeClass('cometchat_tabopen');
                        }
                    });
                }
            },
            flicker:function(){
                return setInterval(function(){
                                    x = flickCtr%2;
                                    jqcc('#cometchat_userstab').css('background',bgs[x]);
                                    flickCtr++;
                                }, 200);
            },
            getTimeDisplay: function(ts){
                var time = getTimeDisplay(ts);
                if((ts+"").length == 10){
                    ts = ts*1000;
                }
                return ts<jqcc.cometchat.getThemeVariable('todays12am') ? time.month+' '+time.date+', '+time.hour+":"+time.minute+' '+time.ap : time.hour+":"+time.minute+time.ap;
            },
            createChatbox: function(id, name, status, message, avatar, link, silent, tryOldMessages){
                if(id==null||id==''){
                    return;
                }
                if(jqcc.cometchat.getThemeArray('buddylistName', id)==null||jqcc.cometchat.getThemeArray('buddylistName', id)==''){
                    if(jqcc.cometchat.getThemeArray('trying', id)===undefined){
                        jqcc.cometchat.setThemeArray('trying', id, 1);
                        if(!isNaN(id)){
                            jqcc.cometchat.createChatboxSet(id, name, status, message, avatar, link, silent, tryOldMessages);
                        }else{
                            setTimeout(function(){
                                if(typeof (jqcc[settings.theme].createChatbox)!=='undefined'){
                                    jqcc[settings.theme].createChatbox(id, jqcc.cometchat.getThemeArray('buddylistName', id), jqcc.cometchat.getThemeArray('buddylistStatus', id), jqcc.cometchat.getThemeArray('buddylistMessage', id), jqcc.cometchat.getThemeArray('buddylistAvatar', id), jqcc.cometchat.getThemeArray('buddylistLink', id), silent, tryOldMessages);
                                }
                            }, 5000);
                        }
                    }else{
                        if(jqcc.cometchat.getThemeArray('trying', id)<5){
                            jqcc.cometchat.incrementThemeVariable('trying['+id+']');
                            setTimeout(function(){
                                if(typeof (jqcc[settings.theme].createChatbox)!=='undefined'){
                                    jqcc[settings.theme].createChatbox(id, jqcc.cometchat.getThemeArray('buddylistName', id), jqcc.cometchat.getThemeArray('buddylistStatus', id), jqcc.cometchat.getThemeArray('buddylistMessage', id), jqcc.cometchat.getThemeArray('buddylistAvatar', id), jqcc.cometchat.getThemeArray('buddylistLink', id), silent, tryOldMessages);
                                }
                            }, 5000);
                        }
                    }
                }else{

                    if(typeof (jqcc[settings.theme].createChatboxData)!=='undefined'){
                        jqcc[settings.theme].createChatboxData(id, jqcc.cometchat.getThemeArray('buddylistName', id), jqcc.cometchat.getThemeArray('buddylistStatus', id), jqcc.cometchat.getThemeArray('buddylistMessage', id), jqcc.cometchat.getThemeArray('buddylistAvatar', id), jqcc.cometchat.getThemeArray('buddylistLink', id), silent, tryOldMessages);
                    }
                }
            },
            createChatboxSuccess: function(id, name, status, message, avatar, link, silent, tryOldMessages, data){
                name = data.n;
                status = data.s;
                message = data.m;
                avatar = data.a;
                link = data.l;
                jqcc.cometchat.setThemeArray('buddylistStatus', id, status);
                jqcc.cometchat.setThemeArray('buddylistMessage', id, message);
                jqcc.cometchat.setThemeArray('buddylistAvatar', id, avatar);
                jqcc.cometchat.setThemeArray('buddylistName', id, name);
                jqcc.cometchat.setThemeArray('buddylistLink', id, link);

                if(chatboxOpened[id]!=null){
                    $("#cometchat_user_"+id+"_popup").find("div.cometchat_tabtitle")
                            .removeClass("cometchat_tabtitle_available")
                            .removeClass("cometchat_tabtitle_busy")
                            .removeClass("cometchat_tabtitle_offline")
                            .removeClass("cometchat_tabtitle_away")
                            .addClass('cometchat_tabtitle_'+status);
                    if($("#cometchat_user_"+id+"_popup").length>0){
                        var banNew = '<div id="cometchat_usercontrol"><div id="cometchat_ban_'+id+'">Ban</div><div>New Window</div></div>'
                        $("#cometchat_user_"+id+"_popup").find("div.cometchat_message").html(banNew);
                    }
                }
                jqcc.cometchat.setThemeArray('trying', id, 5);
                if(id!=null&&id!=''&&name!=null&&name!=''){
                    if(typeof (jqcc[settings.theme].createChatboxData)!=='undefined'){
                        jqcc[settings.theme].createChatboxData(id, name, status, message, avatar, link, silent, tryOldMessages);
                    }
                }
            },
            tooltip: function(id, message, orientation){
                var cometchat_tooltip = $('#cometchat_tooltip');
                cometchat_tooltip.css('display', 'none').removeClass("cometchat_tooltip_left").css('left', '-100000px').find(".cometchat_tooltip_content").html(message);
                var pos = $('#'+id).offset();
                var width = $('#'+id).outerWidth(true);
                if(orientation==1){
                    cometchat_tooltip.css('left', (pos.left+width)).addClass("cometchat_tooltip_left");
                }else{
                    var tooltipWidth = cometchat_tooltip.width();
                    var tooltipHeight = cometchat_tooltip.height();
                    var leftposition = (pos.left+14)-tooltipWidth+11;
                    if (id== 'cometchat_userstab') leftposition = pos.left-11;
                    if(id == 'loggedout') leftposition += 10;
                    var topposition = pos.top-$(window).scrollTop()-tooltipHeight-7;
                    cometchat_tooltip.removeClass("cometchat_tooltip_left").css({'left':leftposition,'top':topposition});
                }
                cometchat_tooltip.css('display', 'block');
            },
            moveBar: function(relativePixels){
            },
            chatTab: function(){
                var cometchat_search = $("#cometchat_search");
                var cometchat_userscontent = $('#cometchat_userscontent');
                cometchat_search.click(function(){
                    var searchString = $(this).val();
                    if(searchString==language[18]){
                        cometchat_search.val('');
                        cometchat_search.addClass('cometchat_search_light');
                    }
                    $('#cometchat_optionsbutton.cometchat_tabclick').click();
                });
                cometchat_search.blur(function(){
                    var searchString = $(this).val();
                    if(searchString==''){
                        cometchat_search.val(language[18]).addClass('cometchat_search_light');
                        $('#cometchat_nousers_found').remove();
                    }
                });
                cometchat_search.keyup(function(event){
                    event.stopImmediatePropagation();
                    if(event.keyCode==27) {
                        $(this).val('').blur();
                    }
                    var searchString = $(this).val();
                    if(searchString.length>0&&searchString!=language[18]){
                        cometchat_userscontent.find("div.cometchat_userlist").hide();
                        var searchResult = cometchat_userscontent.find('div.cometchat_userlist:icontains('+searchString+')').show();
                        var matchLength = searchResult.length;
                        if(matchLength == 0){
                            if($('#cometchat_nousers_found').length == 0) {
                                $('#cometchat_userslist').prepend('<div id="cometchat_nousers_found">'+language[58]+'</div>');
                            }
                        } else {
                            $('#cometchat_nousers_found').remove();
                        }
                        cometchat_search.removeClass('cometchat_search_light');
                    }else{
                        cometchat_userscontent.find('div.cometchat_userlist').show();
                        $('#cometchat_nousers_found').remove();
                    }
                });
                var cometchat_userstab = $('#cometchat_userstab');
                var cometchat_userstab_popup = $("#cometchat_userstab_popup");
                // cometchat_userstab_popup.find("div.cometchat_userstabtitle").click(function(){
                //     cometchat_userstab.click();
                // });
                $('div#cometchat_minimize_userstab_popup').click(function(){
                    cometchat_userstab.click();
                });
                cometchat_userstab.mouseover(function(){
                    $(this).addClass("cometchat_tabmouseover");
                });
                cometchat_userstab.mouseout(function(){
                    $(this).removeClass("cometchat_tabmouseover");
                });
                cometchat_userstab.click(function(){
                    if(jqcc.cometchat.getThemeVariable('offline')==1){
                        jqcc.cometchat.setThemeVariable('offline', 0);
                        jqcc.cometchat.setThemeArray('buddylistStatus', jqcc.cometchat.getThemeVariable('userid'), 'available');
                        jqcc[settings.theme].removeUnderline();
                        $("#cometchat_userstab_icon").addClass('cometchat_user_available2');
                        $('span.cometchat_optionsstatus.available').css('text-decoration', 'underline');
                        //$('#cometchat_userstab_text').html(language[81]);
                        $('#cometchat_userstab_text').html(language[81]+'('+jqcc.tapatalk.updateMessageCount(1)+')');
                        jqcc.cometchat.chatHeartbeat(1);
                        jqcc.cometchat.sendStatus('available');
                        $("#cometchat_optionsbutton_popup").find("span.available").click();
                    }
                    $('#cometchat_optionsbutton_popup').removeClass('cometchat_tabopen').hide();
                    $('#cometchat_optionsbutton').removeClass('cometchat_tabclick');
                    if($(this).hasClass("cometchat_tabclick")){
                        jqcc.cometchat.setSessionVariable('buddylist', '0');
                        cometchat_userstab_popup.addClass("cometchat_tabopen");
                    }else{
                        jqcc.cometchat.setSessionVariable('buddylist', '1');
                        cometchat_userstab_popup.removeClass("cometchat_tabopen");
                        $("#cometchat_tooltip").css('display', 'none');
                        $("span.cometchat_userscontentavatar").find("img").each(function(){
                            if($(this).attr('original')){
                                $(this).attr("src", $(this).attr('original'));
                                $(this).removeAttr('original');
                            }
                        });
                    }
                    $(this).toggleClass("cometchat_tabclick cometchat_userstabclick");
                    if(settings.showSettingsTab==0){
                        $('span.cometchat_userstabclick').addClass('cometchat_extra_width');
                    }
                    cometchat_search.removeClass('cometchat_option_active_serach');
                    $('#cometchat_sidebar').toggle();
                });
            },
            optionsButton: function(){
                var cometchat_optionsbutton_popup = $("#cometchat_optionsbutton_popup");
                cometchat_optionsbutton_popup.find("span.cometchat_gooffline").click(function(){
                    jqcc[settings.theme].goOffline();
                });
                $("#cometchat_soundnotifications").click(function(event){
                    var notification = 'false';
                    if($("#cometchat_soundnotifications").is(":checked")){
                        notification = 'true';
                    }
                    $.cookie(settings.cookiePrefix+"sound", notification, {path: '/', expires: 365});
                });
                $("#cometchat_popupnotifications").click(function(event){
                    var notification = 'false';
                    if($("#cometchat_popupnotifications").is(":checked")){
                        notification = 'true';
                    }
                    $.cookie(settings.cookiePrefix+"popup", notification, {path: '/', expires: 365});
                });
                cometchat_optionsbutton_popup.find("span.available").click(function(event){
                    if(jqcc.cometchat.getThemeArray('buddylistStatus', jqcc.cometchat.getThemeVariable('userid'))!='available'){
                        jqcc.cometchat.setThemeArray('buddylistStatus', jqcc.cometchat.getThemeVariable('userid'), 'available');
                        jqcc[settings.theme].removeUnderline();
                        $("#cometchat_userstab_icon").addClass('cometchat_user_available2');
                        $(this).css('text-decoration', 'underline');
                        jqcc.cometchat.sendStatus('available');
                    }
                });
                cometchat_optionsbutton_popup.find("div.cometchat_statusbutton").click(function(event){
                    jqcc[settings.theme].statusSendMessage();
                });
                $("#guestsname").find("div.cometchat_guestnamebutton").click(function(event){
                    jqcc[settings.theme].setGuestName();
                });
                cometchat_optionsbutton_popup.find("span.busy").click(function(event){
                    if(jqcc.cometchat.getThemeArray('buddylistStatus', jqcc.cometchat.getThemeVariable('userid'))!='busy'){
                        jqcc.cometchat.setThemeArray('buddylistStatus', jqcc.cometchat.getThemeVariable('userid'), 'busy');
                        jqcc[settings.theme].removeUnderline();
                        $("#cometchat_userstab_icon").addClass('cometchat_user_busy2');
                        $(this).css('text-decoration', 'underline');
                        jqcc.cometchat.sendStatus('busy');
                    }
                });
                cometchat_optionsbutton_popup.find("span.invisible").click(function(event){
                    if(jqcc.cometchat.getThemeArray('buddylistStatus', jqcc.cometchat.getThemeVariable('userid'))!='invisible'){
                        jqcc.cometchat.setThemeArray('buddylistStatus', jqcc.cometchat.getThemeVariable('userid'), 'invisible');
                        jqcc[settings.theme].removeUnderline();
                        $("#cometchat_userstab_icon").addClass('cometchat_user_invisible2');
                        $(this).css('text-decoration', 'underline');
                        jqcc.cometchat.sendStatus('invisible');
                    }
                });
                cometchat_optionsbutton_popup.find("textarea.cometchat_statustextarea").keydown(function(event){
                    return jqcc.cometchat.statusKeydown(event, this);
                });
                cometchat_optionsbutton_popup.find("input.cometchat_guestnametextbox").keydown(function(event){
                    return jqcc.cometchat.guestnameKeydown(event, this);
                });
                var cometchat_optionsbutton = $('#cometchat_optionsbutton');
                cometchat_optionsbutton.mouseover(function(){
                    if(!cometchat_optionsbutton_popup.hasClass("cometchat_tabopen")){
                        if(jqcc.cometchat.getThemeVariable('loggedout')==0){
                            if(tooltipPriority==0){
                                jqcc[settings.theme].tooltip('cometchat_optionsbutton', language[0]);
                            }
                        }else{
                            if(tooltipPriority==0){
                                jqcc[settings.theme].tooltip('cometchat_optionsbutton', language[8]);
                            }
                        }
                    }
                    $(this).addClass("cometchat_tabmouseover");
                });
                cometchat_optionsbutton.mouseout(function(){
                    $(this).removeClass("cometchat_tabmouseover");
                    if(tooltipPriority==0){
                        $("#cometchat_tooltip").css('display', 'none');
                    }
                });
                cometchat_optionsbutton.click(function(){
                    if(jqcc.cometchat.getThemeVariable('loggedout')==0){
                        if(jqcc.cometchat.getThemeVariable('offline')==1){
                            jqcc.cometchat.setThemeVariable('offline', 0);
                            $('#cometchat_userstab_text').html(language[81]+' ('+jqcc.cometchat.getThemeVariable('lastOnlineNumber')+')');
                            jqcc.cometchat.chatHeartbeat(1);
                            cometchat_optionsbutton_popup.find(".available").click();
                        }
                        $("#cometchat_tooltip").css('display', 'none');
                        $(this).toggleClass("cometchat_tabclick");
                        cometchat_optionsbutton_popup.toggle().toggleClass("cometchat_tabopen");
                        $('#cometchat_userstab_popup').toggle();
                        $('#cometchat_search').toggleClass('cometchat_option_active_serach');
                        if($(this).hasClass('cometchat_tabclick')){
                            $('#cometchat_userstab_popup').removeClass("cometchat_tabopen");
                            $('#cometchat_userstab').removeClass('cometchat_tabclick');
                            jqcc.cometchat.setSessionVariable('buddylist', '0');
                        } else {
                            $('#cometchat_userstab_popup').addClass("cometchat_tabopen");
                            $('#cometchat_userstab').addClass('cometchat_tabclick');
                            jqcc.cometchat.setSessionVariable('buddylist', '1');
                        }
                        if($.cookie(settings.cookiePrefix+"sound")){
                            if($.cookie(settings.cookiePrefix+"sound")=='true'){
                                $("#cometchat_soundnotifications").attr("checked", true);
                            }else{
                                $("#cometchat_soundnotifications").attr("checked", false);
                            }
                        }
                        if($.cookie(settings.cookiePrefix+"popup")){
                            if($.cookie(settings.cookiePrefix+"popup")=='true'){
                                $("#cometchat_popupnotifications").attr("checked", true);
                            }else{
                                $("#cometchat_popupnotifications").attr("checked", false);
                            }
                        }
                        if(settings.showSettingsTab==1&&settings.showOnlineTab==0){
                            jqcc("#cometchat_chatboxes_wide").find('span').each(function(index){
                                if($('#'+$(this).attr('id')).hasClass('cometchat_tabclick')){
                                    $('#'+$(this).attr('id')).removeClass('cometchat_tabclick').removeClass('cometchat_usertabclick');
                                    $('#'+$(this).attr('id')+'_popup').removeClass('cometchat_tabopen');
                                }
                            });
                        }
                    }else{
                        if(language[16]!=''){
                            location.href = language[16];
                        }
                    }
                });
                cometchat_optionsbutton_popup.find("div.cometchat_userstabtitle").click(function(){
                    $('#cometchat_userstab_popup').show();
                    $('#cometchat_userstab').addClass('cometchat_tabclick').click();
                });
            },
            chatboxKeyup: function(event, chatboxtextarea, id){
                if(event.keyCode==27){
                    event.stopImmediatePropagation();
                    $(chatboxtextarea).val('');
                     $("#cometchat_user_"+id+"_popup").find('div.cometchat_tabtitle').click();
                }else if(event.keyCode==13&&event.shiftKey==0){
                    $(chatboxtextarea).val('');
                }
                var adjustedHeight = chatboxtextarea.clientHeight;
                var maxHeight = 94;
                clearTimeout(typingTimer);
                jqcc.cometchat.setThemeVariable('typingTo', id);
                typingTimer = setTimeout(function(){
                    jqcc.cometchat.resetTypingTo(id);
                }, settings.typingTimeout);
                if(maxHeight>adjustedHeight){
                    adjustedHeight = Math.max(chatboxtextarea.scrollHeight, adjustedHeight);
                    if(maxHeight)
                        adjustedHeight = Math.min(maxHeight, adjustedHeight);

                    if(adjustedHeight>chatboxtextarea.clientHeight){
                        $(chatboxtextarea).css('height', adjustedHeight+4+'px');
                        $("#cometchat_user_"+id+"_popup").find("div.cometchat_tabcontenttext").css('height', ((chatboxHeight)-(adjustedHeight)+23)+'px');
                        $("#cometchat_user_"+id+"_popup").find("div.cometchat_tabcontent > div.slimScrollDiv").css('height', ((chatboxHeight)-(adjustedHeight)+23)+'px');
                    }
                }else{
                    $(chatboxtextarea).css('overflow-y', 'auto');
                }
            },
            chatboxKeydown: function(event, chatboxtextarea, id, force){
                var condition = 1;
                if((event.keyCode==13&&event.shiftKey==0)||force==1){
                    var message = $(chatboxtextarea).val();
                    message = message.replace(/^\s+|\s+$/g, "");
                    $(chatboxtextarea).val('');
                    $(chatboxtextarea).css('height', '16px');
                    $("#cometchat_user_"+id+"_popup").find("div.cometchat_tabcontent > div.slimScrollDiv").css('height', ((chatboxHeight)+10)+'px');
                    $("#cometchat_user_"+id+"_popup").find("div.cometchat_tabcontenttext").css('height', ((chatboxHeight)-1)+'px');
                    $(chatboxtextarea).css('overflow-y', 'hidden');
                    $(chatboxtextarea).focus();
                    if(settings.floodControl){
                        condition = ((Math.floor(new Date().getTime()))-lastmessagetime>2000);
                    }
                    if(message!=''){
                        if(condition){
                            var messageLength = message.length;
                            if(messageLength>1200){
                                message = message.substring(0, 1200)+'...';
                            }
                            lastmessagetime = Math.floor(new Date().getTime());
                            if(jqcc.cometchat.getThemeArray('isJabber', id)!=1){
                                jqcc.cometchat.chatboxKeydownSet(id, message);
                            }else{
                                jqcc.ccjabber.sendMessage(id, message);
                            }
                        }else{
                            alert(language[53]);
                        }
                    }
                    return false;
                }
            },
            scrollBars: function(silent){
            },
            scrollDown: function(id){
                if(jqcc().slimScroll){
                    $('#cometchat_tabcontenttext_'+id).slimScroll({scroll: '1',railAlwaysVisible: true});
                }else{
                    setTimeout(function(){
                        $("#cometchat_tabcontenttext_"+id).scrollTop(50000);
                    }, 100);
                }
            },
            updateChatbox: function(id){
                if(jqcc.cometchat.getThemeArray('isJabber', id)!=1){
                    jqcc.cometchat.updateChatboxSet(id);
                }else{
                    jqcc.ccjabber.getRecentData(id);
                }
            },
            updateChatboxSuccess: function(id, data){
                var name = jqcc.cometchat.getThemeArray('buddylistName', id);
                $("#cometchat_tabcontenttext_"+id).find('div.cometchat_chatboxmessage').remove();
                if(typeof (jqcc[settings.theme].addMessages)!=='undefined'&&data.hasOwnProperty('messages')){
                    jqcc[settings.theme].addMessages(data['messages']);
                }
            },
            windowResize: function(silent){
                 $('#cometchat_base').css('left', settings.barPadding);
                if($('#cometchat_base').length){
                    var baseRight = (settings.showOnlineTab == 1) ? (settings.barPadding+245) : settings.barPadding;
                    $('#cometchat_base').css({'left': 'auto', 'right': baseRight+'px'});
                }
                jqcc[settings.theme].scrollBars(silent);
                $.tapatalk.closeTooltip();
                $.tapatalk.rearrange();
            },
            chatWith: function(id){
                if(id+'' == jqcc.cometchat.getUserID()){
                    return;
                }
                if(jqcc.cometchat.getThemeVariable('loggedout')==0){
                    if(jqcc.cometchat.getThemeVariable('offline')==1){
                        jqcc.cometchat.setThemeVariable('offline', 0);
                        $('#cometchat_userstab_text').html(language[81]+'('+jqcc.tapatalk.updateMessageCount(1)+')');
                        jqcc.cometchat.chatHeartbeat(1);
                        $("#cometchat_optionsbutton_popup").find("span.available").click();
                    }
                    jqcc[settings.theme].createChatbox(id.toString(), jqcc.cometchat.getThemeArray('buddylistName', id), jqcc.cometchat.getThemeArray('buddylistStatus', id), jqcc.cometchat.getThemeArray('buddylistMessage', id), jqcc.cometchat.getThemeArray('buddylistAvatar', id), jqcc.cometchat.getThemeArray('buddylistLink', id));

                    name = jqcc.cometchat.getThemeArray('buddylistName', id);
                    var add = true;
                    if($('#cometchat_userslist').has('#cometchat_userlist_'+id).length){
                        add = false;
                    }

                    if( name != 'undefined' && add)
                    {
                        lastMessage = jqcc.cometchat.getThemeArray('buddylistMessage', id);
                        avatar = jqcc.cometchat.getThemeArray('buddylistAvatar', id);
                        status = jqcc.cometchat.getThemeArray('buddylistStatus', id);

                        if(typeof lastMessage == 'undefined' ){
                            lastMessage = '';
                        }
                        if(typeof avatar == 'undefined' ){
                            avatar = baseUrl+"themes/tapatalk/images/custom/default_avatar.png";
                        }
                        if(typeof status == 'undefined' ){
                            status = 'offline';
                        }

                        lastTime = '0s';

                        offline = '';

                        if(status == 'offline'){
                            offline = 'offline_treatment';
                        }

                        buddylisttempavatar = '<div id="cometchat_userlist_'+id+'" class="cometchat_userlist '+offline+'" onmouseover="jqcc(this).addClass(\'cometchat_userlist_hover\');" onmouseout="jqcc(this).removeClass(\'cometchat_userlist_hover\');"><span class="cometchat_cross" onclick="jqcc.tapatalk.deleteConversation('+id+')" ></span><span class="cometchat_userscontentavatar"><span class="cometchat_usersnewmessages">0</span><img class="cometchat_userscontentavatarimage" src="'+avatar+'"><span class="cometchat_userscontentdot cometchat_'+status+'"></span></span><span class="cometchat_userscontentname"><b>'+name+'</b><p id ="cometchat_lastmessage"><small>'+lastMessage+'</small></p></span><span id="cometchat_chatarrow"></span><span id="cometchat_lastconvo">'+lastTime+'</span></div><div id="cometchat_divider"></div>';
                        if(jqcc('#cometchat_userslist').find('.cometchat_nofriends').length != 0){
                            $('.cometchat_nofriends').remove();
                            $('#cometchat_userslist').append('<div>'+buddylisttempavatar+'</div>');
                        } else{
                            $('#cometchat_userslist div:first').append(buddylisttempavatar);
                        }

                    }
                }
            },
            scrollFix: function(){
                var elements = ['cometchat_base', 'cometchat_userstab_popup', 'cometchat_optionsbutton_popup', 'cometchat_tooltip', 'cometchat_hidden'];

                $.each(chatboxOpened, function(openChatboxId, state){
                    if(state==1){
                        elements.push('cometchat_user_'+openChatboxId+'_popup');
                    }
                });
                for(x in elements){
                    $('#'+elements[x]).css('position', 'absolute');
                    var bottom = parseInt($('#'+elements[x]).css('bottom'));
                    if(x==0){
                        bottom = 0;
                    }
                    var height = parseInt($('#'+elements[x]).height());
                    if(windowHeights[elements[x]]&&x!=3){
                        height = windowHeights[elements[x]];
                    }else{
                        windowHeights[elements[x]] = height;
                    }
                    $('#'+elements[x]).css('top', (parseInt($(window).height())-bottom-height+parseInt($(window).scrollTop()))+'px');
                }
            },
            checkPopups: function(silent){
                $("#cometchat_chatboxes_wide").find("div.cometchat_tabalert").each(function(){
                    var cometchat_chatboxes = $("#cometchat_chatboxes");
                    if(($(this).parent().offset().left<(cometchat_chatboxes.offset().left+cometchat_chatboxes.width()))&&($(this).parent().offset().left-cometchat_chatboxes.offset().left)>=0){
                        $(this).css('display', 'block');
                    }else{
                        $(this).css('display', 'none');
                        if(($(this).parent().offset().left-cometchat_chatboxes.offset().left)>=0){
                            $("#cometchat_chatbox_right").find("div.cometchat_tabalertlr").html(parseInt($("#cometchat_chatbox_right").find("div.cometchat_tabalertlr").html())+parseInt($(this).html()));
                            $("#cometchat_chatbox_right").find("div.cometchat_tabalertlr").css('display', 'block');
                        }
                    }
                });
            },
            launchModule: function(id){
                if($('#cometchat_container_'+id).length == 0){
                    $("#cometchat_trayicon_"+id).click();
                }
            },
            toggleModule: function(id){
                if($('#cometchat_container_'+id).length == 0){
                    $("#cometchat_trayicon_"+id).click();
                }
            },
            closeModule: function(id){
                if(jqcc(document).find('#cometchat_closebox_'+id).length > 0){
                    jqcc(document).find('#cometchat_closebox_'+id)[0].click();
                }
            },
            joinChatroom: function(roomid, inviteid, roomname){
                $("#cometchat_trayicon_chatrooms").click();
                $('#cometchat_trayicon_chatrooms_iframe,.cometchat_embed_chatrooms').attr('src', baseUrl+'modules/chatrooms/index.php?roomid='+roomid+'&inviteid='+inviteid+'&roomname='+roomname+'&basedata='+jqcc.cometchat.getThemeVariable('baseData'));
                jqcc.cometchat.setThemeVariable('openChatboxId', '');
            },
            closeTooltip: function(){
                $("#cometchat_tooltip").css('display', 'none');
            },
            scrollToTop: function(){
                $("html,body").animate({scrollTop: 0}, {"duration": "slow"});
            },
            reinitialize: function(){
                if(jqcc.cometchat.getThemeVariable('loggedout')==1){
                    $("#cometchat_userstab,#cometchat_optionsbutton,#cometchat_chatboxes").show();
                    $('#loggedout').hide();
                    jqcc.cometchat.setThemeVariable('loggedout', 0);
                    jqcc.cometchat.setExternalVariable('initialize', '1');
                    jqcc.cometchat.chatHeartbeat();
                }
            },
            updateHtml: function(id, temp){
                if($("#cometchat_user_"+id+"_popup").length>0){
                    document.getElementById("cometchat_tabcontenttext_"+id).innerHTML = ''+temp+'';
                    jqcc[settings.theme].scrollDown(id);
                }else{
                    if(jqcc.cometchat.getThemeArray('trying', id)===undefined||jqcc.cometchat.getThemeArray('trying', id)<5){
                        setTimeout(function(){
                            $.cometchat.updateHtml(id, temp);
                        }, 1000);
                    }
                }
            },
            updateJabberOnlineNumber: function(number){
                jqcc.cometchat.setThemeVariable('jabberOnlineNumber', number);
                jqcc.cometchat.setThemeVariable('lastOnlineNumber', jqcc.cometchat.getThemeVariable('jabberOnlineNumber')+siteOnlineNumber);
                if(jqcc.cometchat.getThemeVariable('offline')==0){
                    $('#cometchat_userstab_text').html(language[81]+' ('+jqcc.cometchat.getThemeVariable('lastOnlineNumber')+')');
                }
            },
            userClick: function(listing){
                var id = $(listing).attr('id');
                if(typeof id==="undefined"||$(listing).attr('id')==''){
                    id = $(listing).parents('div.cometchat_userlist').attr('id');
                }
                id = id.substr(19);
                if(typeof (jqcc[settings.theme].createChatbox)!=='undefined'){

                    jqcc[settings.theme].createChatbox(id, jqcc.cometchat.getThemeArray('buddylistName', id), jqcc.cometchat.getThemeArray('buddylistStatus', id), jqcc.cometchat.getThemeArray('buddylistMessage', id), jqcc.cometchat.getThemeArray('buddylistAvatar', id), jqcc.cometchat.getThemeArray('buddylistLink', id));
                }
            },
            messageBeep: function(baseUrl){
                $('<audio id="messageBeep" style="display:none;"><source src="'+baseUrl+'mp3/beep.mp3" type="audio/mpeg"><source src="'+baseUrl+'mp3/beep.ogg" type="audio/ogg"><source src="'+baseUrl+'mp3/beep.wav" type="audio/wav"></audio>').appendTo($("body"));
            },
            ccClicked: function(id){
                $(id).click();
            },
            ccAddClass: function(id, classadded){
                $(id).addClass(classadded);
            },
            moveLeft: function(){
                jqcc[settings.theme].moveBar("-=230px");
            },
            moveRight: function(){
                jqcc[settings.theme].moveBar("+=230px");
            },
            processMessage: function(message, self){
                return message;
            },
            minimizeAll: function(){
                $("div.cometchat_tabpopup").each(function(index){
                    if($(this).hasClass('cometchat_tabopen')){
                        $(this).find('div.cometchat_tabtitle').click();
                    }
                });
            },
            iconNotFound: function(image, name){
                $('.'+name+'icon').attr({'src': baseUrl+'modules/'+name+'/icon.png', 'width': '16px'});
            },
            rearrange: function(){
                var ttlWidth = 0;
                visibleTab = [];
                var currUnreadCount = 0;
                var ttlLength = $('#cometchat_chatboxes_wide').children().length;
                $('#cometchat_chatboxes_wide').children().each(function(index){
                    var thisElem = $(this);
                    var left = thisElem.offset().left;
                    var id = thisElem.attr('id').split('_')[2];
                    $('#cometchat_user_'+id+'_popup').css('left',left);
                    if($('#cometchat_user_'+id).outerWidth()+1 == chatboxWidth) {
                        left += 100;
                    }
                    if (left < barVisiblelimit) {
                        var currElem = $('#cometchat_user_'+id+'_popup.cometchat_tabopen');
                        currElem.find('div.cometchat_tabtitle').click();
                        currElem.nextAll('div.cometchat_tabopen').find('div.cometchat_tabtitle').click();
                        var unseenUserCount = ttlLength-index;
                        if(unseenUserCount > 0){
                            $('#cometchat_chatbox_left').find('div.cometchat_tabtext').html(ttlLength-index);
                            currUnreadCount = ttlLength-index;
                        }
                        return false;
                    }
                    visibleTab.push(id);
                    ttlWidth += (thisElem.hasClass('cometchat_tabclick')) ? (chatboxWidth+chatboxDistance) : (237+chatboxDistance);
                });
                $('#cometchat_chatboxes').css('width', ttlWidth+'px');
                $.tapatalk.createUnseenUser();
                if(!currUnreadCount){
                    $('#cometchat_chatbox_left').removeClass('cometchat_unseenList_open').hide();
                    $('#cometchat_unseenUsers').hide();
                    $('#cometchat_chatbox_left_border_fix').hide();
                } else {
                    $('#cometchat_chatbox_left').show();
                    var unreadUnseenCount = $('#cometchat_unseenUsers').find('.unread_msg').length;
                    if(unreadUnseenCount > 0) {
                        $('#cometchat_unseenUserCount').html(unreadUnseenCount).show();
                    } else {
                        $('#cometchat_unseenUserCount').hide();
                    }
                }
            },
            createUnseenUser: function() {
                var unseenUserHtml = ''
                $.each(allChatboxes,function(id){
                   if(visibleTab.indexOf(id) == -1) {
                       var amount = parseInt($('#cometchat_user_'+id).attr('amount'));
                       var countVisible = '';
                       var unreadMsg = '';
                        if(amount > 0) {
                            countVisible = 'style="visibility: visible;" ';
                            unreadMsg = 'unread_msg';
                        }
                       unseenUserHtml += '<div id="unseenUser_'+id+'" class="cometchat_unseenUserList '+unreadMsg+'" uid="'+id+'"><div class="cometchat_unreadCount cometchat_floatL" '+countVisible+'>'+amount+'</div><div class="cometchat_userName cometchat_floatL">'+jqcc.cometchat.getThemeArray('buddylistName', id)+'</div><div class="cometchat_unseenClose cometchat_floatR" uid="'+id+'" >×</div></div>';
                   }
                });
                if(unseenUserHtml == ''){
                    $('#cometchat_chatbox_left').find(".cometchat_unseenList_open").click();
                } else {
                    $('#cometchat_unseenUsers').html(unseenUserHtml);
                }

            },
            swapTab: function(sourceId) {
                var destinationId = visibleTab[visibleTab.length-1] || sourceId;
                var tempElem = $('#cometchat_user_'+sourceId+'_popup').detach();
                $('#cometchat_user_'+destinationId+'_popup').before(tempElem);
                var tempElem = $('#cometchat_user_'+sourceId).detach();
                $('#cometchat_user_'+destinationId).before(tempElem);
                tempElem.click();
                visibleTab.pop();
                visibleTab.push(sourceId);
                $.tapatalk.createUnseenUser();
                $('#cometchat_chatbox_left').find(".cometchat_unseenList_open").click();
            },
            blinkPopupTitle: function(id){
                clearInterval(blinkInterval);
                blinkInterval= setInterval(function(){
                    $('#cometchat_user_'+id+'_popup').find('div.cometchat_tabtitle').toggleClass('cometchat_new_message_titlebar');
                },1000);
            },
            //Custom
            deleteConversation: function(id){
                $('div#cometchat_userlist_'+id).unbind('click');
                var settings = jqcc.cookie('cc_checked');
                if(settings == '1'){
                    jqcc.tapatalk.deleteCommit(id);
                } else{

                    $('span.conf_button').on('click',function(){
                        var txt = $(this).text();
                        if(txt == 'OK'){
                            var id = jqcc.cookie('cc_delete_id');
                            jqcc.tapatalk.deleteCommit(id);
                        } else{
                            $('.conf_popup').fadeOut('slow');
                        }
                    });

                    $('#donotShow').change(function(){
                        if (this.checked) {
                            console.log('checked');
                            jqcc.cookie('cc_checked','1',{expires:365});
                        }
                        else{
                            console.log('Not checked');
                            jqcc.cookie('cc_checked','0',{expires:365});
                        }
                    });

                    $('div.conf_popup').fadeIn('fast');
                    jqcc.cookie('cc_delete_id',id);
                }

            },
            deleteCommit: function(id){
                userid = jqcc.cometchat.getThemeVariable('userid');
                $.ajax({
                    url: baseUrl+"cometchat_delete.php",
                    data: {"to":id ,"from": userid},
                    dataType: 'json',
                    error: function(){
                        alert("Something Went Wrong please try again later");
                        $('div#cometchat_userlist_'+id).bind('click');
                    },
                    success: function(data){
                        $('div#cometchat_userlist_'+id).fadeOut('fast');
                        $('div.conf_popup').fadeOut('slow');
                        $('#cometchat_user_'+id+'_popup').find('.cometchat_closebox').click();
                    }
                });
            },
            getTimeProccessed: function(ts){
                if((ts+"").length == 10){
                        ts = ts*1000;
                    }
                var ob = ts;
                var timeObj = getTimeDisplay(ob);
                var now = new Date().getTime();
                var diff = now - ob;
                var seconds = diff/1000;
                if(seconds <= 60) {
                    return parseInt(seconds)+'s';
                }
                var mins = seconds/60;
                if(mins <= 60){
                    return parseInt(mins)+'m';
                }
                var hr = mins/60;
                if(hr <= 24){
                    return parseInt(hr)+'hr';
                }
                var ret = timeObj.date+'|'+timeObj.month;
                    return ret;
            },
            blockinit:function(id){

                var settings = jqcc.cookie('cc_checked_block');
                if(settings == '1'){
                    jqcc.tapatalk.blockCommit(id);
                } else{

                    $('span.conf_button_block').on('click',function(){
                        var txt = $(this).text();
                        if(txt == 'OK'){
                            jqcc.tapatalk.blockCommit(id);
                        } else{
                            $('div#conf_block_'+id).fadeOut('fast');
                        }
                    });

                    $('#donotShowBlock').change(function(){
                        if (this.checked) {
                            console.log('Block checked');
                            jqcc.cookie('cc_checked_block','1',{expires:365});
                        }
                        else{
                            console.log('Block Not checked');
                            jqcc.cookie('cc_checked_block','0',{expires:365});
                        }
                    });

                    $('div#conf_block_'+id).fadeIn('fast');
                }
            },
            unblockinit:function(id){
                var settings = jqcc.cookie('cc_checked_block');
                if(settings == '1'){
                    jqcc.tapatalk.unblockCommit(id);
                } else{

                    $('span.conf_button_block').on('click',function(){
                        var txt = $(this).text();
                        if(txt == 'OK'){
                            jqcc.tapatalk.unblockCommit(id);
                        } else{
                            $('div#conf_block_'+id).fadeOut('fast');
                        }
                    });

                    $('#donotShowBlock').change(function(){
                        if (this.checked) {
                            console.log('Block checked');
                            jqcc.cookie('cc_checked_block','1',{expires:365});
                        }
                        else{
                            console.log('Block Not checked');
                            jqcc.cookie('cc_checked_block','0',{expires:365});
                        }
                    });
                    $('div#conf_block_'+id).fadeIn('fast');
                }

            },
            blockCommit:function(id){
                $('span#cometchat_ban_'+id).removeClass('cc_ban');
                $('span#cometchat_ban_'+id).addClass('cc_unban');
                $('span#cometchat_ban_'+id).text('Unblock');
                $('span#cometchat_ban_'+id).attr('onClick','jqcc.tapatalk.unblockinit('+id+')');
                jqcc.ccblock.init(id);
                $('div#conf_block_'+id).fadeOut('fast');
                $('div#conf_block_'+id).find('#blockText').html(language[84]+'<label>'+language[83]+'<input type="checkbox" name="donotShowBlock" id="donotShowBlock" ></label>');

                $("#cometchat_userlist_"+id).find('span.cometchat_userscontentdot')
                                .removeClass("cometchat_available")
                                .removeClass("cometchat_busy")
                                .removeClass("cometchat_offline")
                                .removeClass("cometchat_away")
                                .addClass("cometchat_banned");
                jqcc.cometchat.setThemeArray('buddylistStatus', id, 'banned');

            },
            unblockCommit:function(id){
                $.ajax({
                    url: baseUrl+"plugins/block/index.php",
                    data: {"id":id,"action":'unblock','embed':'web' },

                    error: function(){
                        alert("Something Went Wrong please try again later");
                        $('div#conf_block_'+id).fadeOut('fast');
                    },
                    success: function(data){
                        $('span#cometchat_ban_'+id).removeClass('cc_unban');
                        $('span#cometchat_ban_'+id).addClass('cc_ban');
                        $('span#cometchat_ban_'+id).attr('onClick','jqcc.tapatalk.blockinit('+id+')');
                        $('span#cometchat_ban_'+id).text('');
                        $('div#conf_block_'+id).fadeOut('fast');
                        $('div#conf_block_'+id).find('#blockText').html(language[82]+'<label>'+language[83]+'<input type="checkbox" name="donotShowBlock" id="donotShowBlock" ></label>');
                        $("#cometchat_user_"+id+", #cometchat_user_"+id+"_popup").find('div.cometchat_userscontentdot101')
                                .removeClass("cometchat_available")
                                .removeClass("cometchat_busy")
                                .removeClass("cometchat_offline")
                                .removeClass("cometchat_away")
                                .removeClass("cometchat_banned")
                                .addClass('cometchat_available');
                        $("#cometchat_userlist_"+id).find('span.cometchat_userscontentdot')
                                .removeClass("cometchat_available")
                                .removeClass("cometchat_busy")
                                .removeClass("cometchat_offline")
                                .removeClass("cometchat_away")
                                .removeClass("cometchat_banned")
                                .addClass('cometchat_available');
                        jqcc.cometchat.setThemeArray('buddylistStatus', id, 'available');
                    }
                });
            },
            startPopup:function(id){
                $('#cometchat_user_'+id+'_popup').find('.cometchat_closebox').click();
                loadCCPopup(baseUrl+'extensions/mobilewebapp/index.php?id='+id, 'chatbox '+id,"status=0,toolbar=0,menubar=0,directories=0,resizable=0,location=0,status=0,scrollbars=0, width=235,height=360",235,360,'Instant PM '+id,0,1,1,1);
            },
            closeOffline: function(id){
                $('#cometchat_userofflineMessage_'+id).css('display','none');
            },
            updateMessageCount: function(count){
                count = typeof count !== 'undefined' ? count : 0;
                var chatboxes = jqcc.cometchat.getSessionVariable('activeChatboxes');
                sum = 0;
                var cbArray = chatboxes.split(",");
                var i =0;
                for(i=0;i<cbArray.length;i++){
                    var num = cbArray[i].split("|")[1];
                    amount  = parseInt(num);
                    sum = sum + amount;
                    id = cbArray[i].split("|")[0];
                    if(amount==0){
                        $("#cometchat_userlist_"+id).find('span.cometchat_usersnewmessages').hide();
                    }else{
                        $("#cometchat_userlist_"+id).find('span.cometchat_usersnewmessages').text(amount).show();
                    }
                }
                if(isNaN(sum)){
                    sum = 0;
                }
                if(count == 1){
                    return sum;
                }
                $('.cometchat_userstabtitletext').html(language[81]+'('+sum+')');
                $('#cometchat_userstab_text').html(language[81]+'('+sum+')');
            },
            prependMessagesInit: function(id){
                var messages = jqcc('#cometchat_tabcontenttext_'+id).find('.cometchat_chatboxmessage');
                $('#cometchat_prependMessages_'+id).text(language[41]);
                if(messages.length > 0){
                    prepend = messages[0].id.split('_')[2];
                }else{
                    prepend = -1;
                }
                $.ajax({
                    url: baseUrl+"cometchat_receive.php",
                    data: {"prepend":prepend,"chatbox":id},
                    dataType:'json',
                    error: function(){
                        alert("Something Went Wrong please try again later");

                    },
                    success: function(data){
                        var count = 0;
                        $.each(data, function(type, item){
                            if(type == 'messages'){
                                jqcc.tapatalk.prependMessages(item,id);
                            }
                            count = count+1;
                        });
                        if(count == 0){
                            $('#cometchat_prependMessages_'+id).text(language[87]);
                        }
                    }
                });
            },
            prependMessages:function(item,boxid){
                var oldMessages = '';
                var count = 0;
                $.each(item, function(i, incoming){

                    count = count+1;
                    var avatar = baseUrl+"themes/tapatalk/images/noavatar.png";
                    if(jqcc.cometchat.getThemeArray('buddylistAvatar', incoming.from)!=""){
                        avatar = jqcc.cometchat.getThemeArray('buddylistAvatar', incoming.from);
                    }
                    fromname = jqcc.cometchat.getThemeArray('buddylistName', incoming.from);
                    selfstyleAvatar = '<a class="cometchat_floatL" href="'+jqcc.cometchat.getThemeArray('buddylistLink', incoming.from)+'"><img src="'+avatar+'" title="'+fromname+'"/></a>';


                    var processedmessage = incoming.message;
                    if((incoming.message).indexOf('CC^CONTROL_')!=-1){
                        processedmessage = jqcc.cometchat.processcontrolmessage(incoming);
                    }
                    if(processedmessage == null){
                        return;
                    }

                    if(incoming.sent!=null){
                        var ts = incoming.sent;
                        sentdata = jqcc[settings.theme].getTimeDisplay(ts);
                    }

                    var msg = '';
                    if(parseInt(incoming.self)==1){
                        msg = '<div class="cometchat_chatboxmessage" id="cometchat_message_'+incoming.id+'"><div class="cometchat_chatboxmessagecontent cometchat_self cometchat_floatR" title="'+sentdata+'">'+processedmessage+'</div><div class="selfMsgArrow"><div class="after"></div></div></div>';
                    }else{
                        msg = '<div class="cometchat_chatboxmessage" id="cometchat_message_'+incoming.id+'">'+selfstyleAvatar+'<div class="cometchat_chatboxmessagecontent cometchat_floatL" title="'+sentdata+'">'+processedmessage+'</div><div class="msgArrow"><div class="after"></div></div></div></div>';
                    }
                    oldMessages+=msg;
                });

                jqcc('#cometchat_prependMessages_'+boxid).after(oldMessages);
                if(count == 0){
                    $('#cometchat_prependMessages_'+boxid).text(language[87]);
                } else {
                    $('#cometchat_prependMessages_'+boxid).text(language[86]);
                }
            }
        };
    })();
})(jqcc);

if(typeof(jqcc.tapatalk) === "undefined"){
    jqcc.tapatalk=function(){};
}

jqcc.extend(jqcc.tapatalk, jqcc.cctapatalk);

jqcc(window).resize(function(){
    jqcc.tapatalk.windowResize(1);
});

/* for IE8 */
if(!Array.prototype.indexOf){
    Array.prototype.indexOf = function(obj, start){
        for(var i = (start||0), j = this.length; i<j; i++){
            if(this[i]===obj){
                return i;
            }
        }
        return -1;
    }
}

if(!Array.prototype.forEach){
    Array.prototype.forEach = function(fun)
    {
        var len = this.length;
        if(typeof fun!="function")
            throw new TypeError();
        var thisp = arguments[1];
        for(var i = 0; i<len; i++)
        {
            if(i in this)
                fun.call(thisp, this[i], i, this);
        }
    };
}