<?php
require_once __DIR__.'/helpers.php';
$fields=[
'hero_eyebrow'=>['Hero Eyebrow','Handmade with love'],
'hero_heading'=>['Hero Heading','Beautiful Creations, Made to Make You Special.'],
'hero_text'=>['Hero Description','Welcome to Handicraft by Sonia — a place for thoughtful handmade creations. We believe that every handmade piece carries its own beauty, care and story. Our goal is to make special moments even more memorable with creative designs.'],
'story_date'=>['Our Story Date','A Journey That Began in 2017'],
'story_text'=>['Our Story','Handicraft by Sonia began in 2017 with a simple love for handmade creations. My mother loved making crystal pearl bags, while I enjoyed paper crafting. Together, we started creating beautiful hijab pins with floral designs, ribbon bows and delicate pearl details.'],
'who_we_are'=>['Who We Are','A Mother-Daughter Journey. Handicraft by Sonia is a handmade jewellery and accessories brand born from a mother-daughter passion for creativity.'],
'what_we_do'=>['What We Do','Jewellery for Every Occasion. From delicate everyday jewellery to elegant party wear and stunning bridal collections, we create beautiful handmade accessories.'],
'mission'=>['Our Mission','Making Every Moment Special. Our mission is to bring happiness through unique handmade creations, thoughtful designs and quality craftsmanship.'],
'vision'=>['Our Vision','Growing Through Creativity. Our vision is to grow as a trusted handmade jewellery brand, inspiring creativity and bringing beautiful personalized designs to customers.'],
'why_heading'=>['Why Choose Us Heading','Why Choose Us?'],
'why_description'=>['Why Choose Us Description','We want every customer to enjoy a thoughtful, simple and memorable handmade shopping experience.'],
'why_care'=>['Made with Care','We value the effort and attention that go into handmade creations.'],
'why_design'=>['Creative Designs','Explore a variety of designs and request custom ideas that match your taste.'],
'why_gifts'=>['Thoughtful Gifts','Find handmade items that can make celebrations and special days more meaningful.'],
'why_service'=>['Customer Care','We welcome your questions, feedback and ideas. Your experience matters to us.'],
'about_video_1'=>['Portrait Video 1 URL (MP4 Link or assets/videos/... path)','https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerBlazes.mp4'],
'about_video_2'=>['Portrait Video 2 URL (MP4 Link or assets/videos/... path)','https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerEscapes.mp4'],
'about_video_3'=>['Portrait Video 3 URL (MP4 Link or assets/videos/... path)','https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerFun.mp4']
];
$ok='';$err='';
if($_SERVER['REQUEST_METHOD']==='POST'){verify_csrf();try{foreach($fields as $k=>$m){$v=trim((string)($_POST[$k]??''));if(mb_strlen($v)>10000)throw new RuntimeException('A field is too long.');save_setting($pdo,$k,$v);}$ok='About Us content & 3 portrait videos saved successfully!';}catch(Throwable $e){error_log($e->getMessage());$err=$e instanceof RuntimeException?$e->getMessage():'Could not save content.';}}
page_start('Manage About Us');if($ok)echo '<div class="notice">'.ce($ok).'</div>';if($err)echo '<div class="notice error">'.ce($err).'</div>'; ?>
<form method="post" class="card"><?=csrf()?><h2>About Us Content &amp; Portrait Videos</h2><div class="grid">
<?php foreach($fields as $k=>$m):$v=setting($pdo,$k,$m[1]);$long=in_array($k,['hero_text','story_text','who_we_are','what_we_do','mission','vision','why_description','why_care','why_design','why_gifts','why_service'],true);$wide=in_array($k,['about_video_1','about_video_2','about_video_3'],true); ?>
<div class="field <?=($long||$wide)?'full':''?>"><label for="<?=$k?>"><?=ce($m[0])?></label><?php if($long):?><textarea id="<?=$k?>" name="<?=$k?>"><?=ce($v)?></textarea><?php else:?><input id="<?=$k?>" name="<?=$k?>" value="<?=ce($v)?>"><?php endif;?></div>
<?php endforeach;?></div><button class="btn">Save About Us</button></form><?php page_end(); ?>