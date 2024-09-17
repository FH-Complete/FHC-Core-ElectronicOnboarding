INSERT INTO public.tbl_kennzeichentyp (kennzeichentyp_kurzbz, bezeichnung, aktiv) VALUES
('eobRegistrierungsId', 'Electronic Onboarding Registrierungsid', TRUE),
('vbpkAs', 'Statistik vBpk', TRUE),
('vbpkBf', 'Bildung vBpk', TRUE),
('vbpkBmf', 'Transparenzdatenbank vBpk', TRUE)
ON CONFLICT (kennzeichentyp_kurzbz) DO NOTHING;
