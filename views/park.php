<?php echo $this->humanize('park') ?> Park Details! <br/>

<br/>
<?php if($this->park == 'walt-disney-world'): ?>
	<a href="<?php $this->service_page_path('hotels') ?>">Hotels</a> <br/>
<?php else: ?>
	<a href="<?php $this->service_page_path('attractions') ?>">Attractions</a> <br/>
<?php endif ?>

<a href="<?php $this->service_page_path('dining') ?>">Dining</a> <br/>

<br/>