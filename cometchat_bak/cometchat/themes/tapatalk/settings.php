<?php

if (!defined('CCADMIN')) { echo "NO DICE"; exit; }
global $getstylesheet;
global $theme;
$options = array(
    "barPadding"                    => array('textbox','Padding of bar from the end of the window'),
    "showOnlineTab"                 =>array('choice','Show Who`s Online tab'),
    "showSettingsTab"               => array('choice','Show Settings tab'),
    "showModules"                   =>array('choice','Show Modules in Who\'s Online tab'),
    "chatboxHeight"                 => array('textbox','Set the Height of the Chatbox (Minimum Height can be 200)'),
    "chatboxWidth"                 => array('textbox','Set the Width of the Chatbox (Minimum Width can be 230)'), 
    );

if (empty($_GET['process'])) {
    include_once(dirname(__FILE__).DIRECTORY_SEPARATOR.'config.php');
    
    $form = '';
    
    foreach ($options as $option => $result) {
        $req = '';
        if($option == 'chatboxHeight' OR $option == 'chatboxWidth') {
            $req = 'required';
        }
        $form .= '<div id="'.$option.'"><div class="titlelong" >'.$result[1].'</div><div class="element">'; 
        if ($result[0] == 'textbox') {
            $form .= '<input type="text" class="inputbox" name="'.$option.'" value="'.${$option}.'" '.$req.'>';
        }
        if ($result[0] == 'choice') {
            if (${$option} == 1) {
                $form .= '<input type="radio" name="'.$option.'" value="1" checked>Yes <input type="radio" name="'.$option.'" value="0" >No';
            } else {
                $form .= '<input type="radio" name="'.$option.'" value="1" >Yes <input type="radio" name="'.$option.'" value="0" checked>No';
            }
        }
        if ($result[0] == 'dropdown') {
            $form .= '<select  id="'.$option.'Opt" name="'.$option.'">';
            foreach ($result[2] as $opt) {
                if ($opt == ${$option}) {
                    $form .= '<option value="'.$opt.'" selected>'.ucwords($opt);
                } else {
                    $form .= '<option value="'.$opt.'">'.ucwords($opt);
                }
            }
            $form .= '</select>';
        }
        $form .= '</div><div style="clear:both;padding:7px;"></div></div>';
    }
    
    echo <<<EOD
                <!DOCTYPE html>
                <html>
                    <head>
                        <script type="text/javascript" src="../js.php?admin=1"></script>
                        <script type="text/javascript" language="javascript">
                            $(document).ready(function() {
                                if($('#showOnlineTab').find('input:checked').val() == "1") {
                                    $('#showSettingsTab').show();
                                } else {
                                    $('#showSettingsTab').hide();
                                }
                                setTimeout(function(){
                                    resizeWindow();
                                },200);
                                $('#showOnlineTab input:radio').change(function(){
                                    if($(this).val() == '1'){
                                        $('#showSettingsTab').slideDown(500,function(){
                                            resizeWindow();
                                        });
                                    } else {
                                        $('#showSettingsTab').slideUp(500,function(){
                                            resizeWindow();
                                        });
                                    }
                                });
    
                            });
                            
                            function resizeWindow() {
                                window.resizeTo(($("form").outerWidth()+window.outerWidth-$("form").outerWidth()), ($('form').outerHeight()+window.outerHeight-window.innerHeight));
                            }
                        </script>
                        $getstylesheet
                    </head>
                <body>             
                    <form style="height:100%" action="?module=dashboard&action=loadthemetype&type=theme&name=facebook&process=true" onsubmit="return validate();" method="post">
                    <div id="content" style="width:auto">
                            <h2>Settings</h2>
                            <h3>If you are unsure about any value, please skip them</h3>
                            <div>
                                <div id="centernav" style="width:700px">
                                    $form
                                </div>
                            </div>
                            <div style="clear:both;padding:7.5px;"></div>
                            <input type="submit" value="Update Settings" class="button">&nbsp;&nbsp;or <a href="javascript:window.close();">cancel or close</a>
                         <div style="clear:both"></div>
                           </div>    
                        </form>                         
                </body>
                <script>
                    function validate(){
                        if(($("input:[name=chatboxHeight]").val()) < 200) {
                            alert('Height must be greater than 200');
                            return false;
                        } else if(($("input:[name=chatboxWidth]").val()) < 230){
                            alert('Width must be greater than 230');
                            return false;
                        } else {
                            return true;
                        }
                    }
                </script>
                </html>
EOD;
        } else {
            $data = '';
            foreach ($_POST as $option => $value) {
                $data .= '$'.$option.' = \''.$value.'\';'."\t\t\t// ".$options[$option][1]."\r\n";
            }
            if (!empty($data)) {
                configeditor('SETTINGS',$data,0,dirname(__FILE__).DIRECTORY_SEPARATOR.'config.php');
            }
            header("Location:?module=dashboard&action=loadthemetype&type=theme&name=facebook");
       }
?>
