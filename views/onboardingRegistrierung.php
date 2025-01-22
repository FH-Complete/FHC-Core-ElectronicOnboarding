<?php
	$includesArray = array(
		'title' => 'Registrierung verifizieren',
		'bootstrap5' => true,
		'fontawesome6' => true,
		'navigationcomponent' => true,
		'customCSSs' => array(
			'public/extensions/FHC-Core-ElectronicOnboarding/css/onboardingRegistrierung.css'
		)
	);

	$this->load->view('templates/FHC-Header', $includesArray);
?>
<div id="main">
	<div class="container">
		<br>
		<header>
			<h1 class="h2 fhc-hr"><?php echo $this->p->t('onboarding', 'bewerbungVerifzieren') ?></h1>
		</header>
		<br>
		<div class="row">
			<div class="col-lg-11">
				<div class="card mb-4">
					<div class="card-body">
						<div class="row">
							<?php if (isset($onboardingData->personenbild->bilddaten)): ?>
							<div class="col-lg-3 text-center mb-sm-3 mb-md-3 mb-lg-0">
								<img
									src="data:image/gif;base64,<?php echo $onboardingData->personenbild->bilddaten?>"
									class="img-fluid rounded-3"
									alt="photo"
									style="max-width: 250px; max-height: 250px"/>
							</div>
							<?php endif; ?>
							<div class="col-lg-<?php echo (isset($onboardingData->personenbild->bilddaten) ? 9 : 12) ?>">
								<div class="row">
									<div class="col-sm-3">
										<p class="mb-0"><?php echo $this->p->t('onboarding', 'vorname') ?></p>
									</div>
									<div class="col-sm-9">
										<p class="text-muted mb-0"><?php echo $onboardingData->person->vorname; ?></p>
									</div>
								</div>
								<hr>
								<div class="row">
									<div class="col-sm-3">
										<p class="mb-0"><?php echo $this->p->t('onboarding', 'nachname') ?></p>
									</div>
									<div class="col-sm-9">
										<p class="text-muted mb-0"><?php echo $onboardingData->person->familienname; ?></p>
									</div>
								</div>
								<hr>
								<div class="row">
									<div class="col-sm-3">
										<p class="mb-0"><?php echo $this->p->t('onboarding', 'geburtsdatum') ?></p>
									</div>
									<div class="col-sm-9">
										<p class="text-muted mb-0"><?php echo date_format(date_create($onboardingData->person->geburtsdatum), 'd.m.Y'); ?></p>
									</div>
								</div>
								<hr>
					<form
					action="<?php echo site_url("extensions/FHC-Core-ElectronicOnboarding/OnboardingRegistrierung/registerNewOnboarding")?>"
					class="form-inline"
					method="POST">
								<input type="hidden" name="registrationId" value="<?php echo $registrationId ?>"/>
								<label class="form-label" for="verwendung_code"><?php echo $this->p->t('onboarding', 'emailAdresse') ?></label>
								<div class="row">
									<div class="col-sm-12">
										<input
											type="text"
											class="form-control"
											name="email"
											value="<?php echo set_value('email', $email); ?>"
											placeholder="name@example.com"
											aria-label="Email"
											aria-describedby="email-button"/>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-lg-11">
				<div class="text-danger text-center">
					<b><?php echo validation_errors(); ?></b>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-lg-11">
				<div class="card mb-4 card-body">
					<p>
						<?php echo $this->p->t('onboarding', 'bewerbungVerifizierungEinleitung') ?>
					</p>
					<p>
						<?php echo $this->p->t('onboarding', 'bewerbungVerifizierungKontakthinweis', ['https://www.technikum-wien.at/en/infocenter/']) ?>
					</p>
					<p>
						<a
							id="datenschutzSpoiler"
							data-bs-toggle="collapse"
							href="#datenschutzText"
							role="button"
							aria-expanded="false"
							aria-controls="datenschutzText"
							class="link-offset-2 link-offset-3-hover link-underline link-underline-opacity-0 link-underline-opacity-100-hover"
						>
							<?php echo $this->p->t('onboarding', 'bewerbungVerifizierungDatenschutzhinweis') ?>
							<i class="fa-solid fa-caret-down"></i>
						</a>
					</p>
					<div class="collapse" id="datenschutzText">
						<p>
							<?php echo $this->p->t('onboarding', 'bewerbungVerifizierungDatenschutzhinweisText') ?>
						</p>
						<p>
							<?php echo $this->p->t('onboarding', 'bewerbungVerifizierungInformationenDatenschutzGrundverordnung').
								'<br><a
									href="https://www.technikum-wien.at/information-ueber-ihre-rechte-gemaess-datenschutz-grundverordnung"
									target="_blank">
									https://www.technikum-wien.at/information-ueber-ihre-rechte-gemaess-datenschutz-grundverordnung
								</a>';
							?>
						</p>
						<p>
							<?php echo $this->p->t('onboarding', 'bewerbungVerifizierungDatenschutzFragen').
								'<a href="mailto:datenschutz@technikum-wien.at" target="_blank">
									datenschutz@technikum-wien.at
								</a>';
							 ?>
						</p>
					</div>
					<?php if ($confirmDatenschutzerklaerung): ?>
							<div class="form-check">
								<p>
									<input class="form-check-input" type="checkbox" name="zustimmung_datenschutzerklaerung" value="" id="checkbox_zustimmung_datenschutzerklaerung">
									<label class="form-check-label" for="checkbox_zustimmung_datenschutzerklaerung">
										<?php echo $this->p->t('onboarding','zustimmungDatenschutzerklaerung') ?>
									</label>
								</p>
							</div>
					<?php endif; ?>
					<?php if ($confirmDatenuebermittlung): ?>
							<div class="form-check">
								<p>
									<input class="form-check-input" type="checkbox" name="zustimmung_datenuebermittlung" value="" id="checkbox_zustimmung_datenuebermittlung">
									<label class="form-check-label" for="checkbox_zustimmung_datenuebermittlung">
										<?php echo $this->p->t('onboarding','zustimmungDatenuebermittlung') ?>
									</label>
								</p>
							</div>
					<?php endif; ?>
					<p>
						<button type="submit" id="email-button" class="btn btn-primary">
							<?php echo $this->p->t('onboarding', 'bewerbungVerifzieren') ?>
						</button>
					</p>
					</form>
				</div>
			</div>
		</div>
	</div>
</div>

<?php $this->load->view('templates/FHC-Footer', $includesArray); ?>
