INSERT INTO public.tbl_kennzeichentyp (kennzeichentyp_kurzbz, bezeichnung, aktiv) VALUES
('eobRegistrierungsId', 'Electronic Onboarding Registrierungsid', TRUE)
ON CONFLICT (kennzeichentyp_kurzbz) DO NOTHING;
