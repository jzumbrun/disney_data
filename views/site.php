<ul>
	<?php foreach($this->data as $key => $value):?>
		<li>
			<strong><?php echo $this->humanize($key) ?></strong> : &nbsp <?php echo $this->humanize($value) ?>
		</li>

	<?php endforeach ?>
</ul>