<?php 

/*
Plugin Name: Instagram Easy Wall
Description: foto da instagram
Version: 1.0
Author: Giulio Pons
*/


add_action('admin_menu', 'i_e_w_admin_add_page');
function i_e_w_admin_add_page() {
	add_options_page('Instagram Easy Wall', 'Instagram Easy Wall Options', 'manage_options', 'i_e_w', 'i_e_w_options_page');
}


add_action('admin_init', 'i_e_w_admin_init');
function i_e_w_admin_init(){
	register_setting( 'i_e_w_options_group', 'i_e_w_options', 'i_e_w_options_validate' );
	add_settings_section('i_e_w_main', '', 'i_e_w_section_text', 'vlp');

    add_settings_field('i_e_w_secret', 'Token', 'i_e_w_secret', 'vlp', 'i_e_w_main');
    add_settings_field('i_e_w_expire', 'Scadenza token', 'i_e_w_expire', 'vlp', 'i_e_w_main');
}


function i_e_w_section_text() {
    echo '<p>Configura il plugin.</p>';
    echo '<p>Occorre configurare una app Facebook, abilitare Instagram Basic Display e generare un token per il profilo desiderato nella sezione "User Token Generator". <a href="https://developers.facebook.com/docs/instagram-basic-display-api" target="_blank">Documentazione</a></p>';
}

function i_e_w_secret() {
	$options = get_option('i_e_w_options');
	if(empty($options['token'])) $options['token'] = "";
	echo "<input id='i_e_w_secret' name='i_e_w_options[token]' size='50' type='text' value='{$options['token']}'/>";
}

function i_e_w_expire() {
	$options = get_option('i_e_w_options');
	if(empty($options['expires'])) $options['expires'] = "";
	if(!empty($options['expires'])){
       if (time()<$options['expires']) echo "<p>Token valido fino al ".date ("d-m-Y H:i:s",$options['expires'])." (Sarà rinnovato automaticamente)</p>";
       else echo "<p style='color:red'>Token scaduto! Occorre generarne uno nuovo dall'app di facebook</p>";
    }else echo "Salvare le impostazioni per controllare il token";
}


function i_e_w_refresh_token($token){
   return json_decode(file_get_contents("https://graph.instagram.com/refresh_access_token?grant_type=ig_refresh_token&access_token=".urlencode($token)),true);
}
//
// validate options
function i_e_w_options_validate($input) {
    $options = get_option('i_e_w_options');	
    $options['expires']=0;
    $options['token'] = trim($input['token']);
    if (!empty($options['token'])){
        $refresh=i_e_w_refresh_token($options['token']);
        
        if ($refresh && isset($refresh["access_token"]) && isset($refresh["expires_in"])){
            $options['token']=$refresh["access_token"];
            $options['expires']=time()+$refresh["expires_in"];
        }
    }

    
    $input = $options;

    delete_transient( sha1("i_e_w_transient") );
	return $input;
}

//
// display the admin options page
function i_e_w_options_page() {





	?>
	<div class='wrap'>
		<h2>Instagram Easy Wall Options</h2>

		<?php
		if(!function_exists('curl_version')) {
			echo "<p class='description'><b>WARNING:</b> your server doesn't have <code>CURL</code> module enabled. It is needed.</p>";
		}
		?>
		<form action="options.php" method="post">
			<?php settings_fields('i_e_w_options_group'); ?>
			<?php do_settings_sections('vlp'); ?>
			<p>&nbsp;</p>
            <p id='result'></p>
            <p>&nbsp;</p>
			<input name="Submit" type="submit" value="Save Changes" />
		</form>

        <br>
		<b>Code:</b><br>
		<code><pre>
			$instagram=i_e_w_getpics();
			if(is_array($instagram) && !empty($instagram)) {
				for($i=0;$i&lt;count($instagram);$i++) {
					?&gt;
					&lt;a href="&lt;?php echo $instagram[$i]["link"]?&gt;">&lt;img src="&lt;?php echo $instagram[$i]["standard_resolution"]?&gt;"/>&lt;/a>
					&lt;?php
					if($i==5) break;
				}
			}
		</pre></code>

        <script>
        <?php 
            $options = get_option('i_e_w_options');	
            $token= isset($options['token'])? $options['token']:"";
        ?>
        var token=<?php echo json_encode($token)?>;
        if(token!='') jQuery.get( "https://graph.instagram.com/me?fields=id,username,account_type,media_count&access_token="+token, function( data ) {
            jQuery( "#result" ).html("Dati account "+ JSON.stringify(data) );
            
        });
           
        </script>
		

	</div>
	<?php
}



function i_e_w_getpics() {




	// check for cached
    $options = get_option('i_e_w_options');
    
    if(isset($options['token']) && isset($options['expires']) && $options['expires']<time()+3600*24*3){ //SE MANCANO tre giorni alla scadenza del token lo aggiorniamo; 
        $refresh=i_e_w_refresh_token($options['token']);
        
        if ($refresh && isset($refresh["access_token"]) && isset($refresh["expires_in"])){
            $options['token']=$refresh["access_token"];
            $options['expires']=time()+$refresh["expires_in"];

            update_option('i_e_w_options',$options);
        }        
    } 
	$transient_key=sha1("i_e_w_transient");

	$ret=get_transient( $transient_key );
   
    if ($ret !== false ) return unserialize($ret);

    if (!isset($options['token']) || empty($options['token'])) return array();
   
    $out=array();
	
	try {
		$text = @file_get_contents("https://graph.instagram.com/me/media?fields=media_type,id,caption,permalink,media_url,timestamp,thumbnail_url&access_token=".urlencode($options['token']));
	} catch(Exception $e) {
		$text = "Qualcosa è andato storto nel recuperare le foto da Instagram.";
	}
	
    $out_instagram=json_decode($text,true);
    //var_dump($out_instagram);
	if ($out_instagram && isset($out_instagram["data"]) && is_array($out_instagram["data"])){
        foreach($out_instagram["data"] as $in){
            $out[]=array(
                "link"=>$in["permalink"],
                "standard_resolution"=>isset($in["thumbnail_url"])?$in["thumbnail_url"]:$in["media_url"],
                "media_type"=>$in["media_type"],
                "id"=>$in["id"],
                "timestamp"=>$in["timestamp"],
            );
        } 
        set_transient( $transient_key,serialize($out),3000);
    } 
	return $out;


}
