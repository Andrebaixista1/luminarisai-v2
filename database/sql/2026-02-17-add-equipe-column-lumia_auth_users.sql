IF COL_LENGTH('lumia_auth_users', 'equipe') IS NULL
BEGIN
    ALTER TABLE dbo.lumia_auth_users
    ADD equipe NVARCHAR(120) NULL;
END;

UPDATE dbo.lumia_auth_users
SET equipe = 'CEO'
WHERE id = 1;
