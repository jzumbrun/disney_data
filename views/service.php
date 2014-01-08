<ul>
	<!-- Many different ways to list a service -->
	<?php foreach($this->data as $item):
	$this->log('item', $item);
	?>
		<?php if(is_array($item)): ?>
			<?php foreach($item as $it): ?>
				<?php if(is_string($it)): ?>
					<strong><?php echo $it?></strong>
				<?php elseif(is_array($it)): ?>
					<?php foreach($it as $i): ?>
						<li>
							<a href="<?php $this->site_page_path($i->permalink) ?>"><?php echo $i->name ?></a>
						</li>
					<?php endforeach ?>
				<?php elseif(is_object($it)): ?>
					<li>
						<a href="<?php $this->site_page_path($it->permalink) ?>"><?php echo $it->name ?></a>
					</li>
				<?php endif ?>
			<?php endforeach ?>
		<?php elseif(is_object($item)):?>
			
			<?php if(is_array($item->dinings)): ?>
				<strong><?php echo $item->name?></strong>
				<?php foreach($item->dinings as $dining): ?>
					<li>
						<a href="<?php $this->site_page_path($dining->permalink) ?>"><?php echo $dining->name ?></a>
					</li>
				<?php endforeach ?>

			<?php elseif(!empty($item->name)):?>
				<li>
					<a href="<?php $this->site_page_path($item->permalink) ?>"><?php echo $item->name ?></a>
				</li>
			<?php endif ?>
			
		<?php endif ?>
	<?php endforeach ?>
</ul>