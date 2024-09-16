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
	<div id="content">
		<header>
			<h1 class="h2 fhc-hr">Login abschließen für</h1>
		</header>
		<br>
		<div class="row">
			<div class="col-9">
				<table class="table table-bordered">
					<tbody>
						<tr>
							<?php if (isset($onboardingData->personenbild->bilddaten)): ?>
							<td rowspan="3" class="text-center">
								<img src="data:image/gif;base64,<?php echo $onboardingData->personenbild->bilddaten?>"/>
							</td>
							<?php endif; ?>
							<td class="fw-bold">Vorname</td>
							<td><?php echo $onboardingData->person->vorname; ?></td>
						</tr>
						<tr>
							<td class="fw-bold">Nachname</td>
							<td><?php echo $onboardingData->person->familienname; ?></td>
						</tr>
						<tr>
							<td class="fw-bold">Geburtsdatum</td>
							<td><?php echo date_format(date_create($onboardingData->person->geburtsdatum), 'd.m.Y'); ?></td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>
		<br>
		<span class="text-danger"><?php echo validation_errors(); ?></span>
		<form
		action="<?php echo site_url("extensions/FHC-Core-ElectronicOnboarding/OnboardingRegistrierung/registerNewOnboarding")?>"
		class="form-inline"
		method="POST">
			<label class="form-label" for="verwendung_code">E-Mail Adresse</label>
			<div class="row">
				<div class="col-8">
					<input type="text" class="form-control" name="email" value="<?php echo set_value('email'); ?>" placeholder="name@example.com"/>
					<input type="hidden" name="onboardingData" value="<?php echo json_encode($onboardingData);?>">

				</div>
				<div class="col-4">
					<button type="submit" class="btn btn-primary">Login abschließen</button>
				</div>
			</div>
		</form>
	</div>
</div>

<?php $this->load->view('templates/FHC-Footer', $includesArray); ?>
