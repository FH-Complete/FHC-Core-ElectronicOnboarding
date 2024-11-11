<?php
	$includesArray = array(
		'title' => 'Fehler aufgetreten',
		'bootstrap5' => true,
		'fontawesome6' => true,
		'navigationcomponent' => true
	);

	$this->load->view('templates/FHC-Header', $includesArray);
?>
<div id="main">
	<div id="content">
		<header>
			<h1 class="h2 fhc-hr"><?php echo $this->p->t('onboarding', 'fehlerBeiRegistrierung') ?></h1>
		</header>
		<br>
		<div class="row">
			<div class="col-12">
				<?php echo $this->p->t('onboarding', 'fehlerBeiRegistrierungText') ?>
			</div>
		</div>
		<br>
		<div class="row">
			<div class="col-12">
				<a
					href="<?php echo site_url("extensions/FHC-Core-ElectronicOnboarding/OnboardingRegistrierung/startOnboarding") ?>"
					class="btn btn-primary"
					role="button">
					<?php echo $this->p->t('onboarding', 'fehlerBeiRegistrierungNochmalVersuchen') ?>
				</a>
			</div>
		</div>
	</div>
</div>

<?php $this->load->view('templates/FHC-Footer', $includesArray); ?>
