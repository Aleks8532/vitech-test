CREATE TABLE orders (
                        id VARCHAR(20) NOT NULL PRIMARY KEY, -- возможны проблемы с ограниченным кол-ом товаров в 100млн, следует увеличить кол-во знаков
                        sum  INT DEFAULT 0, -- Лучше явно передавать сумму, даже если она равно 0, во избежание неочевидоности поведения заказа без суммы
                        contractor_type SMALLINT,
                        is_paid SMALLINT DEFAULT 0, -- вероятнее всего у заказ будут и иные статусы, можно заменить на поле со статусом
                        createdAt TIMESTAMP DEFAULT NOW() -- следует использовать единообразные нотации
) CHARACTER SET utf8 COLLATE utf8_general_ci engine MyISAM; -- InnoDB считается более надежной при больших объемах данных,
-- а так же поддерживает транзакции, блокировки уровня строк(MyISAM - только уровня таблицы) и ограничение внешних ключей, которые критичны для системы заказов

CREATE TABLE order_products (
                                id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                                order_id VARCHAR(20),
                                product_id INT, -- внешний ключ на товар, если в этой же бд
                                price INT DEFAULT 0, -- так же, лучше передавать явно
                                quantity INT DEFAULT 1,
                                FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL -- при удалении заказа из orders останутся повисшие сторки order_products -> мусор в бд
) CHARACTER SET utf8 COLLATE utf8_general_ci  engine MyISAM; -- InnoDB