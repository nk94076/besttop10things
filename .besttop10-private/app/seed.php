<?php
$db->beginTransaction();
try {
 $cats=[['Tech','tech'],['Shopping','shopping'],['Travel','travel'],['Gadgets','gadgets'],['Home Improvement','home'],['Ecommerce','shopping'],['Beauty & Care','grid'],['Health & Fitness','gadgets'],['Software & Apps','tech'],['Food & Kitchen','home']];
 $q=$db->prepare('INSERT INTO categories (name,slug,icon) VALUES (?,?,?)');
 foreach($cats as [$name,$icon]) $q->execute([$name,slug($name),$icon]);
 $items=[
 [1,'Wireless headphones, reviewed','Comfort, sound and everyday listening. A closer look at what matters.','headphones.jpg',9.2,'Comfortable over-ear fit\nBalanced sound profile','Bulky for light travel'],
 [10,'A better morning brew','From the first pour to the final clean-up. Find your coffee companion.','coffee.jpg',8.8,'Simple controls\nEasy-to-clean carafe','Takes up counter space'],
 [1,'Find your everyday laptop','Performance, portability and value for work, study and everything between.','laptop.jpg',9.0,'Portable design\nVersatile everyday performance','Limited built-in ports'],
 [3,'Pack smarter. Travel lighter.','A thoughtful look at carry-on luggage for your next long weekend.','travel.jpg',8.7,'Easy organization\nCompact carry-on format','Less room for long trips'],
 [5,'Small upgrades, better spaces','Practical ideas for a calmer and more comfortable home.','home.jpg',8.6,'Flexible styling\nUseful everyday improvements','Measure your space first'],
 [4,'Make every day a little smarter','A simple guide to choosing a smartwatch that fits your routine.','watch.jpg',8.9,'Useful activity overview\nConvenient wrist notifications','Regular charging required'],
 [2,'The everyday shopping edit','How to weigh quality, usefulness and value before your next purchase.','shopping.jpg',8.5,'Versatile everyday essentials\nEasy comparison criteria','Availability varies'],
 [6,'Choosing an online store platform','Look beyond the storefront: tools, usability and room to grow.','laptop.jpg',8.4,'Centralized store management\nFlexible catalog options','Ongoing platform costs'],
 [7,'A simpler self-care routine','A considered approach to everyday beauty and personal care essentials.','beauty.jpg',8.3,'Simple daily routine\nCompact essentials','Individual preferences vary'],
 [8,'Build your everyday fitness kit','Explore useful equipment for a more consistent home workout routine.','fitness.jpg',8.7,'Home-friendly equipment\nFlexible routines','Storage space required'],
 [9,'Make room for focused work','An introduction to choosing productivity tools for your daily workflow.','laptop.jpg',8.6,'Organized task planning\nAccessible workspace','Learning curve for new users']
 ];
 $q=$db->prepare('INSERT INTO reviews(category_id,title,slug,excerpt,body,image,score,pros,cons,verdict,author,status,featured,demo,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
 foreach($items as $i=>$r) {
  [$cat,$title,$excerpt,$img,$score,$pros,$cons]=$r;
  $body="This is an illustrative review created to demonstrate the website layout and CMS. The product images, scores and editorial observations are sample content, not the results of a real product test.\n\nWhat to consider\nStart with how you plan to use the product or service. Compare essential features, practical limitations and total cost before deciding which option belongs on your shortlist.\n\nThe everyday experience\nEase of use matters as much as the feature list. Check dimensions, compatibility, maintenance requirements and available support against your own needs.\n\nBefore you decide\nRead the current specifications and return policy from the seller. The editor can replace this example with original research, named products and a documented evaluation.";
  $q->execute([$cat,$title,slug($title),$excerpt,$body,'/assets/'.$img,$score,str_replace('\\n',"\n",$pros),$cons,'A promising option to explore. Replace this sample verdict with your researched conclusion.','Editorial team','published',$i===0?1:0,1,date('c'),date('c')]);
 }
 foreach(['initialized'=>'1','site_name'=>'Best Top 10 Things','tagline'=>'Good choices start with great reviews.','description'=>'Explore in-depth reviews, compare your options, and find what fits your life.'] as $k=>$v) { $q=$db->prepare('INSERT INTO settings(key,value) VALUES (?,?)'); $q->execute([$k,$v]); }
 $db->commit();
} catch(Throwable $e) { $db->rollBack(); throw $e; }
