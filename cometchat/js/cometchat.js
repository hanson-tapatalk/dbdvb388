/*/
 * CometChat
 * Copyright (c) 2014 Inscripts - support@cometchat.com | http://www.cometchat.com | http://www.inscripts.com
 */
 <?php $callbackfn = ''; if (!empty($_GET['callbackfn'])) { $callbackfn = $_GET['callbackfn']; }?>

; if (!Object.keys) Object.keys = function(o) {if (o !== Object(o))throw new TypeError('Object.keys called on a non-object');var k=[],p;for (p in o) if(Object.prototype.hasOwnProperty.call(o,p)) k.push(p);return k;};

(function($){
    $.cometchat = $.cometchat||function(){
        var baseUrl = '<?php echo BASE_URL;?>';
        var ccvariable = {};
        var sendajax = true;
        var broadcastData = [];
        var sendbroadcastinterval = 0;
        <?php echo $settings; ?>
        ccvariable.documentTitle = document.title;
        ccvariable.isJabber = {};
        ccvariable.externalVars = {};
        ccvariable.sendVars = {};
        ccvariable.sessionVars = {};
        ccvariable.internalVars = {};
        ccvariable.openChatboxId = '';
        ccvariable.loggedout = 0;
        ccvariable.offline = 0;
        ccvariable.windowFocus = true;
        ccvariable.resynchronize = 0;
        ccvariable.heartbeatTimer;
        ccvariable.heartbeatTime = settings.minHeartbeat;
        ccvariable.heartbeatCount = 1;
        ccvariable.updateSessionVars = 0;
        ccvariable.timestamp = 0;
        ccvariable.lastOnlineNumber = 0;
        ccvariable.trayOpen = '';
        ccvariable.newMessages = 0;
        ccvariable.buddylistName = {};
        ccvariable.buddylistMessage = {};
        ccvariable.buddylistStatus = {};
        ccvariable.buddylistAvatar = {};
        ccvariable.buddylistLink = {};
        ccvariable.buddylistIsDevice = {};
        ccvariable.jabberOnlineNumber = 0;
        ccvariable.chatBoxesOrder = {};
        ccvariable.trying = {};
        ccvariable.todaysDate = new Date();
        ccvariable.currentTime = Math.floor(ccvariable.todaysDate.getTime());
        ccvariable.todays12am = ccvariable.currentTime -(ccvariable.currentTime%(24*60*60*1000));
        ccvariable.specialChars = /([^\x00-\x80]+)|([&][#])+/;
        ccvariable.typingTo = 0;
        ccvariable.idleTime = ccvariable.currentTime;
        ccvariable.idleFlag = 0;
        ccvariable.currentStatus;
        ccvariable.buddyListHash;
        ccvariable.callbackfn = '<?php echo $callbackfn;?>';
        ccvariable.baseData = $.cookie(settings.cookiePrefix+'data');
        ccvariable.mobileDevice = navigator.userAgent.match(/ipad|ipod|iphone|android|windows ce|Windows Phone|blackberry|palm|symbian/i);
        ccvariable.initialized = 0;
        ccvariable.crossDomain = '<?php echo CROSS_DOMAIN;?>';
        ccvariable.dataMethod = 'get';
        ccvariable.dataTimeout = '10000';
        ccvariable.isMini = 0;
        ccvariable.desktopNotification = {};
        ccvariable.runHeartbeat = 1;
        ccvariable.userid = 0;
        ccvariable.ccmobileauth =0 ;
        ccvariable.prependLimit = (typeof(settings['prependLimit'])!=="undefined")?settings['prependLimit']:0;
        var calleeAPI = settings.theme;
        if(typeof (ccvariable.callbackfn)!='undefined'&&ccvariable.callbackfn!=''){
            calleeAPI = ccvariable.callbackfn;
        }else if(ccvariable.mobileDevice&&settings.disableForMobileDevices){
        	calleeAPI = ccvariable.callbackfn = 'ccmobiletab';
        }

        ccvariable.externalVars["callbackfn"] = ccvariable.callbackfn;
        $.ajaxSetup({scriptCharset: "utf-8", cache: "false"});
        if(settings.messageBeep==1&&ccvariable.callbackfn==""){
            if(typeof (jqcc[calleeAPI].messageBeep)!='undefined'){
                jqcc[calleeAPI].messageBeep(baseUrl);
            }
        }
        var eventMethod = window.addEventListener ? "addEventListener" : "attachEvent";
        var eventer = window[eventMethod];
        var messageEvent = eventMethod == "attachEvent" ? "onmessage" : "message";

        // Listen to message from child window
        eventer(messageEvent,function(e) {
            if(e.data.indexOf('ccmobile_reinitializeauth')!== -1){
                jqcc.ccmobiletab.reinitialize();
            }else if(e.data.indexOf('cc_reinitializeauth')!== -1){
                jqcc.cometchat.reinitialize();
                jqcc('#cometchat_userstab').click();
                jqcc('#cometchat_auth_popup').removeClass('cometchat_tabopen');
                jqcc('#cometchat_optionsbutton').removeClass('cometchat_tabclick');
            }else if(e.data.indexOf('alert')!== -1){
                if(typeof(e.data.split('^')[1]) != 'undefined'){
                    alert(e.data.split('^')[1]);
                }
            }
        },false);
        $(window).focus(function(){
            ccvariable.isMini = 0;
            if(settings.desktopNotifications==1){
                for(x in  ccvariable.desktopNotification){
                    for(y in  ccvariable.desktopNotification[x]){
                        ccvariable.desktopNotification[x][y].close();
                    }
                }
            }
            ccvariable.desktopNotification = {};
        });
        $(window).blur(function(){
            ccvariable.isMini = 1;
        });
        function userClickId(id){
            if(typeof (jqcc[calleeAPI].createChatbox)!=='undefined'){
                jqcc[calleeAPI].createChatbox(id, ccvariable.buddylistName[id], ccvariable.buddylistStatus[id], ccvariable.buddylistMessage[id], ccvariable.buddylistAvatar[id], ccvariable.buddylistLink[id]);
            }
        };
        function branded(){
            language[1] = 'Powered By <a href="http://www.cometchat.com">CometChat</a>';
        };
        function preinitialize(){
            if(jqcc.cometchat.getUserAgent()[0]=="MSIE" && parseInt(jqcc.cometchat.getUserAgent()[1])<9){
                settings.windowFavicon=0;
            };
            if(ccvariable.callbackfn==''&&settings.hideBarCheck==1&&$.cookie(settings.cookiePrefix+"loggedin")!=1){
                $.ajax({
                    url: baseUrl+"cometchat_check.php",
                    data: {'init': '1', basedata: ccvariable.baseData},
                    dataType: 'jsonp',
                    type: ccvariable.dataMethod,
                    timeout: ccvariable.dataTimeout,
                    success: function(data){
                        if(data!='0'){
                            $.cookie(settings.cookiePrefix+"loggedin", '1', {path: '/'});
                            if(typeof (jqcc[calleeAPI].initialize)!=='undefined'){
                                jqcc[calleeAPI].initialize();
                            }
                            ccvariable.externalVars["buddylist"] = '1';
                            ccvariable.externalVars["initialize"] = '1';
                            ccvariable.externalVars["currenttime"] = ccvariable.currentTime;
                            if (ccvariable.runHeartbeat == 1) {
                              jqcc.cometchat.chatHeartbeat();
                            }
                        }
                    }
                });
            }else{
                if(typeof (jqcc[calleeAPI].initialize)!=='undefined'){
                    jqcc[calleeAPI].initialize();
                }else if(ccvariable.callbackfn!=''&&typeof (jqcc[ccvariable.callbackfn].init())=='function'){
                    jqcc[ccvariable.callbackfn].init();
                }
                ccvariable.externalVars["buddylist"] = '1';
                ccvariable.externalVars["initialize"] = '1';
                ccvariable.externalVars["currenttime"] = ccvariable.currentTime;
                if(ccvariable.runHeartbeat==1){
                    jqcc.cometchat.chatHeartbeat();
                }
            }
        };
        arguments.callee.getUserAgent = function(){
            var ua= navigator.userAgent, tem,
            M= ua.match(/(opera|chrome|safari|firefox|msie|trident(?=\/))\/?\s*(\d+)/i) || [];
            if(/trident/i.test(M[1])){
                tem=  /\brv[ :]+(\d+)/g.exec(ua) || [];
                return 'IE '+(tem[1] || '');
            }
            if(M[1]=== 'Chrome'){
                tem= ua.match(/\bOPR\/(\d+)/);
                if(tem!= null) return 'Opera '+tem[1];
            }
            M= M[2]? [M[1], M[2]]: [navigator.appName, navigator.appVersion, '-?'];
            if((tem= ua.match(/version\/(\d+)/i))!= null) M.splice(1, 1, tem[1]);
            return M;
        };
        arguments.callee.chatHeartbeat = function(force){
            var newMessage = 0;
            if(force==1){
                if(typeof window.cometcall_function=='function'){
                    cometcall_function(cometid, 0, calleeAPI);
                }
            }
            ccvariable.externalVars["typingto"] = ccvariable.typingTo;
            ccvariable.externalVars["blh"] = ccvariable.buddyListHash;
            ccvariable.externalVars["status"] = "";
            if((ccvariable.callbackfn!=''&&ccvariable.callbackfn!='desktop')||calleeAPI=='ccmobiletab'){
                ccvariable.externalVars["status"] = 'available';
            }
            if(force==1){
                ccvariable.externalVars["f"] = 1;
            }else{
                delete ccvariable.externalVars["f"];
            }
            var atleastOneNewMessage = 0;
            var nowTime = new Date();
            var n = {};
            var idleDifference = Math.floor(nowTime.getTime()/1000)-ccvariable.idleTime;
            if(idleDifference>settings.idleTimeout&&ccvariable.idleFlag==0){
                if(ccvariable.currentStatus=='available'||ccvariable.currentStatus=='busy'){
                    ccvariable.idleFlag = 1;
                    ccvariable.externalVars["status"] = 'away';
                    if(typeof (jqcc[calleeAPI].removeUnderline2)!=='undefined'){
                        jqcc[calleeAPI].removeUnderline2();
                    }
                    if(typeof (jqcc[calleeAPI].ccAddClass)!=='undefined'){
                        jqcc[calleeAPI].ccAddClass("#cometchat_userstab_icon", 'cometchat_user_away2');
                    }
                }
            }
            if(idleDifference<settings.idleTimeout&&ccvariable.idleFlag==1){
                if(ccvariable.currentStatus=='available'||ccvariable.currentStatus=='busy'){
                    ccvariable.idleFlag = 0;
                    ccvariable.externalVars["status"] = ccvariable.currentStatus;
                    if(typeof (jqcc[calleeAPI].removeUnderline2)!=='undefined'){
                        jqcc[calleeAPI].removeUnderline2();
                    }
                    if(typeof (jqcc[calleeAPI].ccAddClass)!=='undefined'){
                        jqcc[calleeAPI].ccAddClass("#cometchat_userstab_icon", 'cometchat_user_'+ccvariable.currentStatus+'2');
                    }
                }
            }
            if(ccvariable.crossDomain==1){
                ccvariable.externalVars["cookie_"+settings.cookiePrefix+"state"] = $.cookie(settings.cookiePrefix+'state');
                ccvariable.externalVars["cookie_"+settings.cookiePrefix+"hidebar"] = $.cookie(settings.cookiePrefix+'hidebar');
                ccvariable.externalVars["cookie_"+settings.cookiePrefix+"an"] = $.cookie(settings.cookiePrefix+'an');
                ccvariable.externalVars["cookie_"+settings.cookiePrefix+"loggedin"] = $.cookie(settings.cookiePrefix+'loggedin');
            }
            ccvariable.externalVars['currenttime'] = Math.floor(new Date().getTime()/1000);
            ccvariable.externalVars["basedata"] = ccvariable.baseData;
            $.ajax({
                url: baseUrl+"cometchat_receive.php",
                data: ccvariable.externalVars,
                dataType: 'jsonp',
                type: ccvariable.dataMethod,
                timeout: ccvariable.dataTimeout,
				error: function(){
                    clearTimeout(ccvariable.heartbeatTimer);
                    ccvariable.heartbeatTime = settings.minHeartbeat;
                    ccvariable.heartbeatTimer = setTimeout(function(){
                        jqcc.cometchat.chatHeartbeat();
                    }, ccvariable.heartbeatTime);
				},
                success: function(data){
					if(data){
                        $.each(data, function(type, item){
                            if(type=='blh'){
                                ccvariable.buddyListHash = item;
                            }
                            if(type=='an'){
                                if(typeof (jqcc[calleeAPI].newAnnouncement)!=='undefined'){
                                    jqcc[calleeAPI].newAnnouncement(item);
                                }
                            }
                            if(type=='buddylist'){
                                if(typeof (jqcc[calleeAPI].buddyList)=='function'){
                                    jqcc[calleeAPI].buddyList(item);
                                }
                            }
                            if(type=='loggedout'){
                                $.cookie(settings.cookiePrefix+"loggedin", null, {path: '/'});
                                $.cookie(settings.cookiePrefix+"state", null, {path: '/'});
                                $.cookie(settings.cookiePrefix+"jabber", null, {path: '/'});
                                $.cookie(settings.cookiePrefix+"jabber_type", null, {path: '/'});
                                $.cookie(settings.cookiePrefix+"hidebar", null, {path: '/'});
                                if(typeof (jqcc[calleeAPI].loggedOut)!=='undefined'){
                                    jqcc[calleeAPI].loggedOut();
                                }
                                jqcc.cometchat.setThemeVariable('loggedout', 1);
                                clearTimeout(ccvariable.heartbeatTimer);
                            }
                            if(type=='userstatus'){
                                ccvariable.userid = item.id;
                                ccvariable.buddylistStatus[item.id] = item.s;
                                ccvariable.buddylistMessage[item.id] = item.m;
                                ccvariable.buddylistName[item.id] = item.n;
                                ccvariable.buddylistAvatar[item.id] = item.a;
                                ccvariable.buddylistLink[item.id] = item.l;
                                ccvariable.ccmobileauth = item.ccmobileauth;
                                if(typeof (jqcc[calleeAPI].userStatus)!=='undefined'){
                                    jqcc[calleeAPI].userStatus(item);
                                }
                            }
                            if(type=='cometid'){
                                cometcall_function(item.id, 0, calleeAPI);
                            }
                            if(type=='init'){
                                jqcc.cometchat.setInternalVariable('updatingsession', '1');
                            }
                            if(type=='initialize'){
                                ccvariable.timestamp = item;
                                ccvariable.externalVars["timestamp"] = item;
                                if(typeof (jqcc[calleeAPI].resynch)!=='undefined'){
                                    jqcc[calleeAPI].resynch();
                                }
                            }
                            if(type=='tt'){
                                if(typeof (jqcc[calleeAPI].typingTo)!=='undefined'){
                                    jqcc[calleeAPI].typingTo(item);
                                }
                            }
                            if(type=='messages'){
                                if(ccvariable.externalVars['initialize'] != 1){
                                    ccvariable.externalVars["timestamp"] = item[Object.keys(item).sort().reverse()[0]].id;
                                }
                                if(typeof (jqcc[calleeAPI].addMessages)!=='undefined'){
                                    newMessage = jqcc[calleeAPI].addMessages(item);
                                }
								ccvariable.heartbeatCount = 1;
								ccvariable.heartbeatTime = settings.minHeartbeat;
                            }
                        });
                        if(ccvariable.openChatboxId!=''&&newMessage>0){
                            if(typeof (jqcc[calleeAPI].tryAddMessages)!=='undefined'){
                                jqcc[calleeAPI].tryAddMessages(ccvariable.openChatboxId, atleastOneNewMessage);
                            }
                        }
                        jqcc.cometchat.setExternalVariable('initialize', '0');
                        jqcc.cometchat.setExternalVariable('currenttime', '0');
                        if(ccvariable.loggedout!=1&&ccvariable.offline!=1){
                            ccvariable.heartbeatCount++;
                            if(ccvariable.heartbeatCount>4){
                                ccvariable.heartbeatTime *= 2;
                                ccvariable.heartbeatCount = 1;
                            }
                            if(ccvariable.heartbeatTime>settings.maxHeartbeat){
                                ccvariable.heartbeatTime = settings.maxHeartbeat;
                            }
                            clearTimeout(ccvariable.heartbeatTimer);
                            ccvariable.heartbeatTimer = setTimeout(function(){
                                jqcc.cometchat.chatHeartbeat();
                            }, ccvariable.heartbeatTime);
                        }
                    }
				}
            });
        };
        arguments.callee.setExternalVariable = function(name, value){
            ccvariable.externalVars[name] = value;
        };
        arguments.callee.setInternalVariable = function(name, value){
            ccvariable.internalVars[name] = value;
        };
        arguments.callee.getSessionVariable = function(name){
            if(ccvariable.sessionVars[name]){
                return ccvariable.sessionVars[name];
            }else{
                return '';
            }
        };
        arguments.callee.incrementThemeVariable = function(name){
            ccvariable[name]++;
        };
        arguments.callee.setThemeVariable = function(name, value){
            ccvariable[name] = value;
        };
        arguments.callee.setThemeArray = function(name, id, value){
            ccvariable[name][id] = value;
        };
        arguments.callee.getThemeArray = function(name, id){
            return ccvariable[name][id];
        };
        arguments.callee.getThemeVariable = function(name){
            return ccvariable[name];
        };
        arguments.callee.userClick = function(listing){
            if(typeof (jqcc[calleeAPI].userClick)!=='undefined'){
                jqcc[calleeAPI].userClick(listing);
            }
        };
        arguments.callee.orderChatboxes = function(){
            var activeids = '';
            var selfNewMessages = 0;
            for(chatbox in ccvariable.chatBoxesOrder){
                if(ccvariable.chatBoxesOrder.hasOwnProperty(chatbox)){
                    if(ccvariable.chatBoxesOrder[chatbox]!=null){
                        if(!Number(ccvariable.chatBoxesOrder[chatbox])){
                            ccvariable.chatBoxesOrder[chatbox] = 0;
                        }
                        activeids += chatbox+'|'+ccvariable.chatBoxesOrder[chatbox]+',';
                        if(ccvariable.chatBoxesOrder[chatbox]>0){
                            selfNewMessages = 1;
                        }
                    }
                }
            }
            ccvariable.newMessages = selfNewMessages;
            activeids = activeids.slice(0, -1);
            jqcc.cometchat.setSessionVariable('activeChatboxes', activeids);
        };
        arguments.callee.getInternalVariable = function(name){
            if(ccvariable.internalVars[name]){
                return ccvariable.internalVars[name];
            }else{
                return '';
            }
        };
        arguments.callee.getExternalVariable = function(name){
            if(ccvariable.externalVars[name]){
                return ccvariable.externalVars[name];
            }else{
                return '';
            }
        };
        arguments.callee.setSessionVariable = function(name, value){
            ccvariable.sessionVars[name] = value;
            if(jqcc.cometchat.getInternalVariable('updatingsession')!=1){
                var cc_state = '';
                if(ccvariable.sessionVars['buddylist']){
                    cc_state += ccvariable.sessionVars['buddylist'];
                }else{
                    cc_state += ' ';
                }
                cc_state += ':';
                if(ccvariable.sessionVars['activeChatboxes']){
                    cc_state += ccvariable.sessionVars['activeChatboxes'];
                }else{
                    cc_state += ' ';
                }
                cc_state += ':';
                if(ccvariable.sessionVars['openChatboxId']){
                    cc_state += ccvariable.sessionVars['openChatboxId'];
                }else{
                    cc_state += ' ';
                }
                cc_state += ':'+ccvariable.lastOnlineNumber;
                cc_state += ':'+ccvariable.offline;
                cc_state += ':'+ccvariable.trayOpen;
                $.cookie(settings.cookiePrefix+'state', cc_state, {path: '/'});
            }
        };
        var windowHeights = {};
        arguments.callee.c5 = function(){
            branded();
            preinitialize();
            return;
        };
        arguments.callee.c6 = function(){
            preinitialize();
            return;
        };
        arguments.callee.getBaseData = function(){
            return ccvariable.baseData;
        };
        arguments.callee.getActiveId = function(){
            return ccvariable.openChatboxId;
        };
        arguments.callee.getUserID = function(){
           return ccvariable.userid;
        };
        arguments.callee.getUser = function(id, callbackfn){
            $.ajax({
                url: baseUrl+"cometchat_getid.php",
                data: {userid: id, basedata: ccvariable.baseData},
                cache: false,
                dataType: 'jsonp',
                type: ccvariable.dataMethod,
                timeout: ccvariable.dataTimeout,
                success: function(data){
                    if(data){
                        window[callbackfn](data);
                    }else{
                        window[callbackfn](0);
                    }
                }
            });
        };
        arguments.callee.ping = function(){
            return 1;
        };
        arguments.callee.getLanguage = function(id){
            if(typeof(id) != 'undefined' && id != null && id != ''){
                if(typeof(language[id]) != 'undefined' ){
                    return language[id];
                }else{
                    return '';
                }
            }
            return language;
        };
        arguments.callee.chatWith = function(id){
            if(typeof (jqcc[calleeAPI].chatWith)!=='undefined'){
                jqcc[calleeAPI].chatWith(id);
            }
        };
        arguments.callee.getRecentData = function(id){
            $.ajax({
                cache: false,
                url: baseUrl+"cometchat_receive.php",
                data: {chatbox: id, basedata: ccvariable.baseData},
                dataType: 'jsonp',
                type: ccvariable.dataMethod,
                timeout: ccvariable.dataTimeout,
                success: function(data){
                    if(ccvariable.callbackfn!=''){
                        jqcc[ccvariable.callbackfn].loadData(id, data);
                    }
                }
            });
        };
        arguments.callee.getUserDetails = function(id){
            $.ajax({
                url: baseUrl+"cometchat_getid.php",
                data: {userid: id, basedata: ccvariable.baseData},
                type: ccvariable.dataMethod,
                timeout: ccvariable.dataTimeout,
                cache: false,
                async: false,
                dataType: 'jsonp',
                success: function(data){
                    ccvariable.buddylistMessage[id] = data.m;
                    ccvariable.buddylistName[id] = data.n;
                    ccvariable.buddylistAvatar[id] = data.a;
                    ccvariable.buddylistLink[id] = data.l;
                    if(ccvariable.callbackfn!=''){
                        jqcc[ccvariable.callbackfn].loadUserData(id, data);
                    }
                }
            });
        };
        arguments.callee.launchModule = function(id){
            if(typeof (jqcc[calleeAPI].launchModule)!=='undefined'){
                jqcc[calleeAPI].launchModule(id);
            }
        };
        arguments.callee.toggleModule = function(id){
            if(typeof (jqcc[calleeAPI].toggleModule)!=='undefined'){
                jqcc[calleeAPI].toggleModule(id);
            }
        };
        arguments.callee.closeModule = function(id){
            if(typeof (jqcc[calleeAPI].closeModule)!=='undefined'){
                jqcc[calleeAPI].closeModule(id);
            }
        };
        arguments.callee.joinChatroom = function(roomid, inviteid, roomname){
            if(typeof (jqcc[calleeAPI].joinChatroom)!=='undefined'){
                jqcc[calleeAPI].joinChatroom(roomid, inviteid, roomname);
            }
        };
        arguments.callee.createChatboxSet = function(id, name, status, message, avatar, link, silent, tryOldMessages){
            $.ajax({
                url: baseUrl+"cometchat_getid.php",
                data: {userid: id, basedata: ccvariable.baseData},
                dataType: 'jsonp',
                type: ccvariable.dataMethod,
                timeout: ccvariable.dataTimeout,
                cache: false,
                success: function(data){
                    if(data){
                        jqcc[settings.theme].createChatboxSuccess(id, name, status, message, avatar, link, silent, tryOldMessages, data);
                    }
                },
                error: function(data){
                    jqcc.cometchat.setThemeVariable('trying', id, 5);
                }
            });

        };
        arguments.callee.updateChatboxSet = function(id,prepend){
            var postVars={chatbox: id, basedata: ccvariable.baseData};
            if(typeof(prepend)!=="undefined"){
                postVars["prepend"]=prepend;
            }
            $.ajax({
                cache: false,
                url: baseUrl+"cometchat_receive.php",
                data: postVars,
                type: ccvariable.dataMethod,
                timeout: ccvariable.dataTimeout,
                dataType: 'jsonp',
                success: function(data){
                    if(data){
                        if(typeof(prepend)!=="undefined"){
                            jqcc[settings.theme].prependMessages(id, data);
                        }else{
                            jqcc[settings.theme].updateChatboxSuccess(id, data);
                        }
                    }
                }
            });
        };
        arguments.callee.chatboxKeydownSet = function(id, message, callbackfn){
            if(typeof(callbackfn) === "undefined" || callbackfn !="") {
                callbackfn = ccvariable.callbackfn;
            }
            ccvariable.sendVars["callbackfn"] = callbackfn;
            if(message.length>1000){
                if(message.charAt(1000)==' '){
                    messagecurrent = message.substring(0, 1000);
                }else{
                    messagecurrent = message.substring(0, 1000);
                    var spacePos = messagecurrent.length;
                    while(messagecurrent.charAt(spacePos)!=' '){
                        spacePos--;
                    }
                    messagecurrent = message.substring(0, spacePos);
                }
                messagenext = message.substring(messagecurrent.length);
                if(messagenext.length>0){
                    messagecurrent = messagecurrent+"...";
                }
            }else{
                messagecurrent = message;
                messagenext = '';
            }
            message = messagecurrent;

            sendAjax = function (broadcastflag) {
                sendajax = false;
                $.ajax({
                    url: baseUrl+"cometchat_send.php",
                    data: ccvariable.sendVars,
                    dataType: 'jsonp',
                    type: ccvariable.dataMethod,
                    timeout: ccvariable.dataTimeout,
                    success: function(data){
                        ccvariable.sendVars = {};
                        if(data != null && typeof(data) != 'undefined'){

                            if(typeof (jqcc[calleeAPI].addMessages)!=='undefined'){
                                if(broadcastflag)
                                {
                                    jqcc[calleeAPI].addMessages(data);
                                }else{
                                    jqcc[calleeAPI].addMessages([{"from": id, "message": data.m, "id": data.id}]);
                                }
                            }

                                /*For Legacy Apps Push Notifications Start*/
                                /*$.each(data, function(i, buddy){
                                    if(i%2!=0 && (jqcc.cometchat.getThemeArray('buddylistIsDevice', buddy)==1)){
                                        jqcc.ccmobilenativeapp.sendnotification(message, buddy.id, jqcc.cometchat.getName(jqcc.cometchat.getThemeVariable('userid')));
                                    }
                                });*/
                                /*For Legacy Apps Push Notifications End*/
                        }
                        jqcc.cometchat.resetTypingTo(id);
                        ccvariable.heartbeatCount = 1;
                        if(ccvariable.heartbeatTime>settings.minHeartbeat){
                            ccvariable.heartbeatTime = settings.minHeartbeat;
                            clearTimeout(ccvariable.heartbeatTimer);
                            ccvariable.heartbeatTimer = setTimeout(function(){
                                jqcc.cometchat.chatHeartbeat();
                            }, ccvariable.heartbeatTime);
                        }
                        ccvariable.sendVars = {};
                        sendajax = true;
                   },
                   error: function(data){
                        sendajax = true;
                        if(broadcastData.length==0){
                            sendbroadcastinterval = 0;
                            clearInterval(sendbroadcastinterval);
                        }
                   }
               });
            }
            $( document ).ajaxStop(function() {
                sendajax = true;
                if(broadcastData.length==0){
                    sendbroadcastinterval = 0;
                    clearInterval(sendbroadcastinterval);
                }
            });
            if(sendajax == true){
                ccvariable.sendVars["basedata"] = ccvariable.baseData;
                if(broadcastData.length == 0){
                    ccvariable.sendVars["to"] = id;
                    ccvariable.sendVars["message"] = message;
                    var broadcastflag = 0;
                }else{
                    broadcastData.push(id,message);
                    ccvariable.sendVars["broadcast"] = broadcastData;
                    var broadcastflag = 1;
                }
                sendAjax(broadcastflag);
            }else{
                broadcastData.push(id,message);
                if(sendbroadcastinterval == 0){
                    sendbroadcastinterval = setInterval(function(){
                        sendbroadcastinterval = 0;
                        clearInterval(sendbroadcastinterval);
                        if(broadcastData.length == 0){
                            clearInterval(sendbroadcastinterval);
                        }
                        if(sendajax == true && broadcastData.length > 0){
                            sendbroadcastinterval = 0;
                            clearInterval(sendbroadcastinterval);
                            ccvariable.sendVars["basedata"] = ccvariable.baseData;
                            ccvariable.sendVars["broadcast"] = broadcastData;
                            sendAjax(1);
                            broadcastData = [];
                        }
                    }, 50);
                }
            }
            if(messagenext.length>0){
                jqcc.cometchat.chatboxKeydownSet(id, '...'+messagenext);
            }
        };

        arguments.callee.sendMessage = function(id, message){
            jqcc.cometchat.chatboxKeydownSet(id,message);
        };

        arguments.callee.addMessage = function(boxid,message,msgid){
            if(typeof (jqcc[calleeAPI].addMessages)!=='undefined'){
                jqcc[calleeAPI].addMessages([{"from": boxid, "message": message, "self": 1, "old": 1, "id": msgid, "sent": Math.floor(new Date().getTime())}]);
            }
            if(typeof (jqcc[calleeAPI].scrollDown)!=='undefined'){
                jqcc[calleeAPI].scrollDown(boxid);
            }
        };

        arguments.callee.statusSendMessageSet = function(message, statustextarea){
            $.ajax({
                url: baseUrl+"cometchat_send.php",
                data: {statusmessage: message, basedata: ccvariable.baseData},
                dataType: 'jsonp',
                type: ccvariable.dataMethod,
                timeout: ccvariable.dataTimeout,
                success: function(data){
                    jqcc[settings.theme].statusSendMessageSuccess(statustextarea);
                },
                error: function(data){
                    jqcc[settings.theme].statusSendMessageError();
                }
            });
        };
        arguments.callee.setGuestNameSet = function(guestname, guestnametextarea){
            $.ajax({
                url: baseUrl+"cometchat_send.php",
                data: {guestname: guestname, basedata: ccvariable.baseData},
                dataType: 'jsonp',
                type: ccvariable.dataMethod,
                timeout: ccvariable.dataTimeout,
                success: function(data){
                    jqcc[settings.theme].setGuestNameSuccess(guestnametextarea);
                },
                error: function(data){
                    jqcc[settings.theme].setGuestNameError();
                }
            });
        };
        arguments.callee.hideBar = function(){
            if(typeof (jqcc[calleeAPI].hideBar)!=='undefined'){
                jqcc[calleeAPI].hideBar();
            }
        };
        arguments.callee.getBaseUrl = function(){
            return baseUrl;
        };
        arguments.callee.setAlert = function(id, number){
            if(typeof (jqcc[calleeAPI].setModuleAlert)!=='undefined'){
                jqcc[calleeAPI].setModuleAlert(id, number);
            }
        };
        arguments.callee.closeTooltip = function(){
            if(typeof (jqcc[calleeAPI].closeTooltip)!=='undefined'){
                jqcc[calleeAPI].closeTooltip();
            }
        };
        arguments.callee.scrollToTop = function(){
            if(typeof (jqcc[calleeAPI].scrollToTop)!=='undefined'){
                jqcc[calleeAPI].scrollToTop();
            }
        };
        arguments.callee.reinitialize = function(){
            ccvariable.baseData = $.cookie(settings.cookiePrefix+'data');
            if(typeof (jqcc[calleeAPI].reinitialize)!=='undefined'){
                jqcc[calleeAPI].reinitialize();
            }
        };
        arguments.callee.updateHtml = function(id, temp){
            if(typeof (jqcc[calleeAPI].updateHtml)!=='undefined'){
                jqcc[calleeAPI].updateHtml(id, temp);
            }
        };
        arguments.callee.processMessage = function(id, value){
            if(typeof (jqcc[calleeAPI].processMessage)!=='undefined'){
                return jqcc[calleeAPI].processMessage(id, value);
            }
        };
        arguments.callee.replaceHtml = function(id, value){
            replaceHtml(id, value);
        };
        arguments.callee.getSettings = function(e){
            return settings;
        };

        arguments.callee.getTrayicon = function(e){
            return trayicon;
        };
        arguments.callee.getCcvariable = function(e){
            return ccvariable;
        };
        arguments.callee.echo = function(e){
            return "ECHO";
        };
        arguments.callee.userAdd = function(id, s, m, n, a, l){
            ccvariable.isJabber[id] = 1;
            ccvariable.buddylistStatus[id] = s;
            ccvariable.buddylistMessage[id] = m;
            ccvariable.buddylistName[id] = n;
            ccvariable.buddylistAvatar[id] = a;
            ccvariable.buddylistLink[id] = l;
        };
        arguments.callee.updateJabberOnlineNumber = function(number){
            if(typeof (jqcc[calleeAPI].updateJabberOnlineNumber)!=='undefined'){
                jqcc[calleeAPI].updateJabberOnlineNumber(number);
            }
        };
        arguments.callee.getName = function(id){
            if(typeof (ccvariable.buddylistName[id])!=='undefined'){
                return ccvariable.buddylistName[id];
            }
        };
        arguments.callee.lightbox = function(name){
            var allowpopout = 0;
            if(trayicon[name]){
                if(name=='chatrooms'||name=='games'||name=='broadcast'){
                    allowpopout = 1;
                    if(settings.theme == 'lite' && name=='chatrooms'){
                        jqcc[calleeAPI].minimizeOpenChatbox();
                    }
                }
                loadCCPopup(trayicon[name][2]+'?', trayicon[name][0], "status=0,toolbar=0,menubar=0,directories=0,resizable=0,location=0,status=0,scrollbars=0, width="+(Number(trayicon[name][4])+2)+",height="+trayicon[name][5]+"", Number(trayicon[name][4])+2, trayicon[name][5], trayicon[name][1], 0, 0, 0, allowpopout);
            }
            ccvariable.openChatboxId = '';
            jqcc.cometchat.setSessionVariable('openChatboxId', ccvariable.openChatboxId);
        };
        arguments.callee.sendStatus = function(message){
            $.ajax({
                url: baseUrl+"cometchat_send.php",
                data: {status: message, basedata: ccvariable.baseData},
                dataType: 'jsonp',
                type: ccvariable.dataMethod,
                timeout: ccvariable.dataTimeout,
                success: function(data){
                    if(message!='away'){
                        ccvariable.currentStatus = message;
                    }
                }
            });
        };
        arguments.callee.tryClickSync = function(id){
            if(ccvariable.buddylistName[id]==null||ccvariable.buddylistName[id]==''){
                if(ccvariable.trying[id]<5){
                    setTimeout(function(){
                        jqcc.cometchat.tryClickSync(id);
                    }, 500);
                }
            }else{
                if(typeof (jqcc[calleeAPI].ccClicked)!=='undefined'){
                    jqcc[calleeAPI].ccClicked('#cometchat_user_'+id);
                }
            }
        };
        arguments.callee.userDoubleClick = function(listing){
            var id = listing;
            if(typeof (jqcc[calleeAPI].createChatbox)!=='undefined'){
                jqcc[calleeAPI].createChatbox(id, ccvariable.buddylistName[id], ccvariable.buddylistStatus[id], ccvariable.buddylistMessage[id], ccvariable.buddylistAvatar[id], ccvariable.buddylistLink[id], 1);
            }
        };
        arguments.callee.tryClick = function(id){
            if(ccvariable.buddylistName[id]==null||ccvariable.buddylistName[id]==''){
                if(ccvariable.trying[id]<5){
                    setTimeout(function(){
                        jqcc.cometchat.tryClick(id);
                    }, 500);
                }
            }else{
                if(ccvariable.openChatboxId!=id){
                    if(typeof (jqcc[calleeAPI].ccClicked)!=='undefined'){
                        jqcc[calleeAPI].ccClicked('#cometchat_user_'+id);
                    }
                }
            }
        };
        arguments.callee.notify = function(title, image, message, clickEvent, id, msgid){
            if(navigator.userAgent.match(/chrome|firefox/i)&&settings.desktopNotifications==1&&ccvariable.idleFlag){
				 if (Notification.permission !== 'denied') {
					Notification.requestPermission(function (permission) {
						if(!('permission' in Notification)) {
							Notification.permission = permission;
						}
					});
				}
                if(Notification.permission === "granted"&&typeof title!='undefined'&&typeof image!='undefined'&&typeof message!='undefined'){
                    tempMsg = jqcc('<div>'+message+'</div>');
                    jqcc.each(tempMsg.find('img.cometchat_smiley'),function(){
                        jqcc(this).replaceWith('*'+jqcc(this).attr('title')+'*');
                    });
                    message = tempMsg.text();
            		if(typeof id!='undefined'){
                        if(typeof ccvariable.desktopNotification[id]=="undefined"){
                            ccvariable.desktopNotification[id] = {};
                        }
                        ccvariable.desktopNotification[id][msgid] = new Notification(title, {icon: image, body: message});
                        ccvariable.desktopNotification[id][msgid].onclick = function(){
                            if(typeof clickEvent=='function'){
                                clickEvent();
                            }
                        };
                    }else{
                        ccvariable.desktopNotification[id][msgid] = new Notification(title, {icon: image, body: message});
                        ccvariable.desktopNotification[id][msgid].onclick = function(){
                            if(typeof clickEvent=='function'){
                                clickEvent();
                            }
                        };
                    }
                }
			}
		};
        arguments.callee.statusKeydown = function(event, statustextarea){
            if(event.keyCode==13&&event.shiftKey==0){
                if(typeof (jqcc[calleeAPI].statusSendMessage)!=='undefined'){
                    jqcc[calleeAPI].statusSendMessage();
                }
                return false;
            }
        };
        arguments.callee.guestnameKeydown = function(event, statustextarea){
            if(event.keyCode==13&&event.shiftKey==0){
                if(typeof (jqcc[calleeAPI].setGuestName)!=='undefined'){
                    jqcc[calleeAPI].setGuestName(statustextarea);
                }
                return false;
            }
        };
        arguments.callee.resetTypingTo = function(id){
            if(ccvariable.typingTo==id){
                ccvariable.typingTo = 0;
            }
        };
        arguments.callee.minimizeAll = function(){
            if(typeof (jqcc[calleeAPI].setGuestName)!=='undefined'){
                jqcc[settings.theme].minimizeAll();
            }
        };
        arguments.callee.processcontrolmessage = function(incoming){
           var message = incoming.message;
           var message_array = message.split('_');
           return jqcc['cc'+message_array[2].toLowerCase()].processControlMessage(message_array);
        };
    };
    $.expr[':'].icontains = function(a, i, m){
        return (a.textContent||a.innerText||"").toLowerCase().indexOf(m[3].toLowerCase())>=0;
    };
    function replaceHtml(el, html){
        var oldEl = typeof el==="string" ? document.getElementById(el) : el;
        /*@cc_on // Pure innerHTML is slightly faster in IE
         oldEl.innerHTML = html;
         return oldEl;
         @*/
        var newEl = oldEl.cloneNode(false);
        newEl.innerHTML = html;
        oldEl.parentNode.replaceChild(newEl, oldEl);
        /* Since we just removed the old element from the DOM, return a reference
         to the new element, which can be used to restore variable references. */
        return newEl;
    };
})(jqcc);
jqcc(document).bind('keyup', function(e){
    if(e.keyCode==27){
        jqcc.cometchat.minimizeAll();
    }
});
<?php if(defined('USE_COMET')&&USE_COMET==1) { ?>
function cometready(){
    jqcc(document).ready(function(){
        if(typeof CometChathasBeenRun==='undefined'){
            CometChathasBeenRun = true;
        }else{
            return;
        }
        jqcc.cometchat();
            jqcc.cometchat.<?php echo $jsfn; ?>();
            if(jqcc('.cometchat_embed_chatrooms').attr('name')=='cometchat_chatrooms_iframe'){
            cometchat_chatrooms_iframe.jqcc.cometchat.setChatroomVars('apiAccess', 1);
        }
    });
};
<?php }else { ?>
jqcc(document).ready(function(){
    if(typeof CometChathasBeenRun==='undefined'){
        CometChathasBeenRun = true;
    }else{
        return;
    }
    jqcc.cometchat();
    jqcc.cometchat.<?php echo $jsfn; ?>();
});
<?php } ?>