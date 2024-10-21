<?php
	$includesArray = array(
		'title' => 'Login abschließen',
		'bootstrap5' => true,
		'fontawesome6' => true,
		'navigationcomponent' => true
	);

	$this->load->view('templates/FHC-Header', $includesArray);
?>
<div id="main">
	<div class="container">
		<br>
		<header>
			<h1 class="h2 fhc-hr">Login abschließen für</h1>
		</header>
		<br>
		<div class="row">
			<div class="col-lg-11">
				<div class="card mb-4">
					<div class="card-body">
						<div class="row">
							<?php if (isset($onboardingData->personenbild->bilddaten)): ?>
							<div class="col-lg-3 text-center mb-3 mb-md-0">
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
										<p class="mb-0">Vorname</p>
									</div>
									<div class="col-sm-9">
										<p class="text-muted mb-0"><?php echo $onboardingData->person->vorname; ?></p>
									</div>
								</div>
								<hr>
								<div class="row">
									<div class="col-sm-3">
										<p class="mb-0">Nachname</p>
									</div>
									<div class="col-sm-9">
										<p class="text-muted mb-0"><?php echo $onboardingData->person->familienname; ?></p>
									</div>
								</div>
								<hr>
								<div class="row">
									<div class="col-sm-3">
										<p class="mb-0">Geburtsdatum</p>
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
									<label class="form-label" for="verwendung_code">E-Mail Adresse</label>
									<div class="row">
										<div class="col-sm-12 input-group">
											<input
												type="text"
												class="form-control"
												name="email"
												value="<?php echo set_value('email', $email); ?>"
												placeholder="name@example.com"
												aria-label="Email"
												aria-describedby="email-button"/>
											<button type="submit" id="email-button" class="btn btn-primary">Login abschließen</button>
										</div>
									</div>
								</div>
							</form>
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
	</div>
</div>

<?php $this->load->view('templates/FHC-Footer', $includesArray); ?>
