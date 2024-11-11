<?php
	$includesArray = array(
		'title' => 'Mail versandt',
		'bootstrap5' => true,
		'fontawesome6' => true,
		'navigationcomponent' => true,
		'customCSSs' => array(
			'public/extensions/FHC-Core-ElectronicOnboarding/css/onboardingVerificationMailSent.css'
		)
	);

	$this->load->view('templates/FHC-Header', $includesArray);
?>
<div id="main">
	<div class="row">
		<div class="card alert-success col-md-9 mt-md-3 mx-auto">
			<div class="card-body">
				<p>
				<span id="mail_icon" class="fa fa-envelope fa-2xl"></span>
				</p>
				<p>
					<?php echo $this->p->t('onboarding', 'emailGesendet', [$email]); ?>
				</p>
				<p>
					<?php
						echo $this->p->t(
							'onboarding',
							'emailGesendetHinweis',
							['<a href="https://www.technikum-wien.at/studienberatung-kontaktieren/" target="_blank">', '</a>']
						);
					?>
				</p>
			</div>
		</div>
	</div>
</div>

<?php $this->load->view('templates/FHC-Footer', $includesArray); ?>
