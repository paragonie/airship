CREATE OR REPLACE FUNCTION update_modtime()
    RETURNS TRIGGER AS $body$
    BEGIN
        NEW.modified = NOW();
        RETURN NEW;
    END;
    $body$ language 'plpgsql';

