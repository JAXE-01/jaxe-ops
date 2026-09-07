ALTER TABLE livrable_items
    MODIFY COLUMN statut ENUM('Planifie','En production','Pret','Publie','Annule','Exclu') DEFAULT 'Planifie';
