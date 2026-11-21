<?php
require_once __DIR__.'/helpers.php';
$fields=[
'contact_email'=>['Email Address','handicraft.by.sonia@gmail.com'],
'contact_phone'=>['Phone Number','01537312870'],
'facebook_url'=>['Facebook Page URL','https://www.facebook.com/Handicraft.by.sonia'],
'instagram_url'=>['Instagram URL','https://www.instagram.com/handicraft_by_soniaa'],
'contact_address'=>['Business Address',''],
'google_maps_embed_url'=>['Google Maps Embed URL','https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3689.914871099135!2d91.81231170000002!3d22.356842999999994']
];
$ok='';$err='';
if($_SERVER['REQUEST_METHOD']==='POST'){verify_csrf();try{foreach($fields as $k=>$m){$v=trim((string)($_POST[$k]??''));if(mb_strlen($v)>2000)throw new RuntimeException('A field is too long.');if($k==='contact_email'&&$v!==''&&!filter_var($v,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Invalid email address.');if(in_array($k,['facebook_url','instagram_url'],true)&&$v!==''&&!filter_var($v,FILTER_VALIDATE_URL))throw new RuntimeException('Enter a valid social URL.');save_setting($pdo,$k,$v);}$ok='Contact information saved in database. Customer page integration is the next step.';}catch(Throwable $e){error_log($e->getMessage());$err=$e instanceof RuntimeException?$e->getMessage():'Could not save contact information.';}}
page_start('Manage Contact Information');if($ok)echo '<div class="notice">'.ce($ok).'</div>';if($err)echo '<div class="notice error">'.ce($err).'</div>'; ?>
<form method="post" class="card"><?=csrf()?><h2>Contact Details</h2>
<?php foreach($fields as $k=>$m):$v=setting($pdo,$k,$m[1]);?><div class="field"><label for="<?=$k?>"><?=ce($m[0])?></label><?php if(in_array($k,['contact_address','google_maps_embed_url'],true)):?><textarea id="<?=$k?>" name="<?=$k?>"><?=ce($v)?></textarea><?php else:?><input id="<?=$k?>" name="<?=$k?>" value="<?=ce($v)?>" <?=$k==='contact_email'?'type="email"':($k==='contact_phone'?'type="tel"':'type="url"')?>><?php endif;?></div><?php endforeach;?>
<button class="btn">Save Contact Information</button></form><?php page_end(); ?>