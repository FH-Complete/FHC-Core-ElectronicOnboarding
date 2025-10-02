INSERT INTO system.tbl_app (app) VALUES
('onboarding')
ON CONFLICT (app) DO NOTHING;
