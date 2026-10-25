CREATE TABLE IF NOT EXISTS business_promotion_place_scopes (
 code TEXT PRIMARY KEY,
 FOREIGN KEY(code) REFERENCES business_promotion_codes(code)
);
CREATE TABLE IF NOT EXISTS business_promotion_places (
 code TEXT NOT NULL,
 destination_id INTEGER NOT NULL,
 PRIMARY KEY(code,destination_id),
 FOREIGN KEY(code) REFERENCES business_promotion_codes(code),
 FOREIGN KEY(destination_id) REFERENCES destinations(id)
);
CREATE TABLE IF NOT EXISTS business_promotion_redemption_places (
 subscription_id INTEGER PRIMARY KEY,
 destination_id INTEGER NOT NULL,
 FOREIGN KEY(subscription_id) REFERENCES business_promotion_redemptions(subscription_id),
 FOREIGN KEY(destination_id) REFERENCES destinations(id)
);
INSERT INTO business_promotion_places(code,destination_id)
SELECT c.code,d.id FROM business_promotion_codes c CROSS JOIN destinations d
WHERE d.publication_status='published'
 AND NOT EXISTS(SELECT 1 FROM business_promotion_place_scopes s WHERE s.code=c.code)
 AND NOT EXISTS(SELECT 1 FROM business_promotion_places p WHERE p.code=c.code AND p.destination_id=d.id);
INSERT INTO business_promotion_places(code,destination_id)
SELECT DISTINCT r.code,b.destination_id FROM business_promotion_redemptions r JOIN businesses b ON b.id=r.business_id
WHERE NOT EXISTS(SELECT 1 FROM business_promotion_place_scopes s WHERE s.code=r.code)
 AND NOT EXISTS(SELECT 1 FROM business_promotion_places p WHERE p.code=r.code AND p.destination_id=b.destination_id);
INSERT INTO business_promotion_place_scopes(code)
SELECT c.code FROM business_promotion_codes c WHERE NOT EXISTS(SELECT 1 FROM business_promotion_place_scopes s WHERE s.code=c.code);
INSERT INTO business_promotion_redemption_places(subscription_id,destination_id)
SELECT r.subscription_id,b.destination_id FROM business_promotion_redemptions r JOIN businesses b ON b.id=r.business_id
WHERE NOT EXISTS(SELECT 1 FROM business_promotion_redemption_places p WHERE p.subscription_id=r.subscription_id);
