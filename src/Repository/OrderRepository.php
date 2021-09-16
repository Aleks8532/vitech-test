<?php

namespace App\Repository;

// Глобальный неймспайс у \PDO можно заменить на импорт и далее использовать PDO
use App\Entity\Item;
use App\Entity\Order;

class OrderRepository
{
    /*
     * Все данные передаются в запросы напрямую, минуя prepare
     * так же отсутвует проверка на валидность введеных пользователем данных, риск SQL инъекций
     *
     * Не следет использовать * в селектах, нужно пречислить все необходимые поля
     */

    /** @var \PDO */
    protected $pdo;

    /**
     * @param \PDO $pdo
     */
    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function save(Order $order)
    {
        /*
         * Сохранение следует обернуть в try catch, в try - транзакцию, в catch - rollBack, иначе можем потерять консистентность данных
         *
         * В запросах где-то есть пробелы после запятых, где-то нет, лучше писать в одном стиле
         */
        $sql = "INSERT INTO orders (id, sum, contractor_type) VALUES ({$order->id}, {$order->sum}, {$order->contractorType})";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        foreach ($order->items as $item) {
            $sql = "INSERT 
                INTO order_products (order_id,product_id,price,quantity) 
                VALUES ({$order->id},{$item->getProductId()},{$item->getPrice()}, {$item->getQuantity()})";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
        }
    }

    /** @return Order */
    public function get($orderId)
    {
        $sql = "SELECT * FROM orders WHERE id={$orderId} LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $data = $stmt->fetch();

        /*
         * Нет обработки отсутсвия заказа в базе
         */

        $order = new Order($data['id']);
        $order->contractorType = $data['contractor_type'];
        $order->isPaid = $data['is_paid'];
        $order->sum = $data['sum'];
        $order->items = $this->getOrderItems($data['id']);

        return $order;
    }

    /** @return Order[] */
    public function last($limit = 10)
    {
        $sql = "SELECT * FROM orders ORDER BY createdAt DESC LIMIT {$limit}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $data = $stmt->fetchAll();
        $orders = [];
        foreach ($data as $item) {
            $order = new Order($item['id']);
            $order->contractorType = $item['contractor_type'];
            $order->isPaid = $item['is_paid'];
            $order->sum = $item['sum'];
            $order->items = $this->getOrderItems($item['id']);
            $orders[] = $order;
        }
        return $orders;
    }

    /*
     * Следует создать репозиторий для сущности Item, и запрашивать позиции заказа там
     * Так же в возвращаемых позициях товара будет отсутствовать id, хотя в базе он уже есть
     */

    public function getOrderItems($orderId)
    {
        $sql = "SELECT * FROM order_products WHERE order_id={$orderId}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $data = $stmt->fetchAll();

        $items = [];
        foreach ($data as $item) {
            $items[] = new Item($item['order_id'], $item['product_id'], $item['price'], $item['quantity']);
        }
        return $items;
    }
}

