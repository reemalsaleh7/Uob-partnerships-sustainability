CREATE TABLE agreement_documents (
    document_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    agreement_id BIGINT NOT NULL,
    file_name VARCHAR(255),
    file_path TEXT,
    document_type VARCHAR(100),
    uploaded_by BIGINT,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(agreement_id) REFERENCES agreements(agreement_id) ON DELETE CASCADE,
    FOREIGN KEY(uploaded_by) REFERENCES users(user_id)
);
