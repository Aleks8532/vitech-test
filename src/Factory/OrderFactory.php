<?php

namespace App\Factory;

// Глобальный неймспайс у \PDO можно заменить на импорт и далее использовать PDO
use App\Entity\Item;
use App\Entity\Order;

class OrderFactory
{

    /** @var \PDO */
    protected $pdo;

    // Излишний докблок, эта информация и так известна
    /**
     * OrderFactory constructor.
     * @param \PDO $pdo
     */
    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // генерацию ID не стоит реализовывать в фабрике
    public function generateOrderId()
    {

        /*
         * В id хранится строка формата "2020-09-1234", если и необходим такой формат идентификатора заказа (я бы подумал о целесообразности)
         * то стоит выделять только последнюю часть "1234" либо сразу в SQL, а лучше в PHP, и явно преобразовывать тип в int, для последующего прибалвения 1
         *
         * Так же имееть место быть конкурирующим запросам, пока новый заказ не сохранился, а следующий уже начал обрабатываться, id заказа задублируется
         * возможно генерацию ид стоит выполнять в транзакции вместе с сохранением
         *
         */
        $sql = "SELECT id FROM orders ORDER BY createdAt DESC LIMIT 1";
        $result = $this->pdo->query($sql)->fetch();
        // Дату стоит передавать параметром, для удобного тестирования
        return (new \DateTime())->format("Y-m") . "-" . $result['id'] + 1;
    }

    public function createOrder($data, $id)
    {
        $order = new Order($id);
        /*
         * Следует явно заполнять объект, используя DTO для data, мы будем точно знать, какие есть поля,
         * непонадобится foreach и if, станет более читаемо
         */
        foreach ($data as $key => $value)
        {
            if ($key == 'items')
            {
                foreach ($value as $itemValue) {
                    $order->items[] = new Item($id, $itemValue['productId'], $itemValue['price'], $itemValue['quantity']);
                }
                continue;
            }
            //использовать сеттеры для свойств заказа
            $order->{$key} = $value;
        }
        return $order;
    }
}