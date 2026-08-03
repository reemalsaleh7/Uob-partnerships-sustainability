UPDATE partners
SET partner_type = CASE
    WHEN UPPER(REGEXP_REPLACE(TRIM(partner_type), '[^A-Za-z0-9]+', '_', 'g'))
        IN ('PUBLIC', 'GOVERNMENT', 'GOVERNMENT_ORGANIZATION',
            'PUBLIC_GOVERNMENT', 'PUBLIC_AUTHORITY', 'MINISTRY')
        THEN 'PUBLIC_GOVERNMENT'
    WHEN UPPER(REGEXP_REPLACE(TRIM(partner_type), '[^A-Za-z0-9]+', '_', 'g'))
        IN ('COMPANY', 'PRIVATE', 'BUSINESS', 'CORPORATION',
            'INNOVATION_HUB', 'TRAINING_PROVIDER')
        THEN 'PRIVATE'
    WHEN UPPER(REGEXP_REPLACE(TRIM(partner_type), '[^A-Za-z0-9]+', '_', 'g'))
        IN ('UNIVERSITY', 'ACADEMIC', 'ACADEMIC_NETWORK',
            'RESEARCH_CENTER', 'RESEARCH_CENTRE', 'RESEARCH_INSTITUTE')
        THEN 'ACADEMIC'
    WHEN UPPER(REGEXP_REPLACE(TRIM(partner_type), '[^A-Za-z0-9]+', '_', 'g'))
        IN ('NONPROFIT', 'NON_PROFIT', 'NGO', 'FOUNDATION', 'CHARITY')
        THEN 'NON_PROFIT'
    ELSE partner_type
END
WHERE partner_type IS NOT NULL;

UPDATE partners
SET profile = CASE organization_name
    WHEN 'Bahrain Institute of Technology'
        THEN 'An academic and technical-education organization supporting applied learning, technology development, and workforce preparation in Bahrain.'
    WHEN 'Gulf Research Centre'
        THEN 'A research organization supporting regional studies, evidence-based collaboration, and knowledge exchange across Gulf priority areas.'
    WHEN 'Future Skills Foundation'
        THEN 'A non-profit organization focused on employability, digital skills, professional development, and future-ready learning opportunities.'
    WHEN 'Bahrain Digital Innovation Hub'
        THEN 'A private-sector innovation hub supporting applied technology projects, specialist mentoring, entrepreneurship, and digital capability development.'
    WHEN 'Gulf Centre for Public Health'
        THEN 'An academic research centre supporting public-health studies, multidisciplinary training, responsible data use, and research translation.'
    WHEN 'Regional Academic Exchange Network'
        THEN 'An academic network supporting student and faculty mobility, collaborative learning, and regional higher-education exchange.'
    WHEN 'Arabian Renewable Energy Institute'
        THEN 'An academic research institute focused on renewable energy, sustainability, applied research, and specialist capacity building.'
    WHEN 'Bahrain Cloud Skills Academy'
        THEN 'A private training organization focused on cloud computing, digital infrastructure, professional certification, and workforce development.'
    WHEN 'Coastal and Marine Research Centre'
        THEN 'An academic research centre focused on marine science, coastal resilience, environmental data, and applied conservation research.'
    ELSE organization_name
        || ' is an existing organization in the University partnership directory. Its brief profile should be verified and expanded when the Agreement is next reviewed.'
END,
updated_at = CURRENT_TIMESTAMP
WHERE (profile IS NULL OR BTRIM(profile) = '')
  AND EXISTS (
      SELECT 1
      FROM agreement_partners ap
      WHERE ap.partner_id = partners.partner_id
  );
