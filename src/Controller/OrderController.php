<?php

namespace App\Controller;

use App\Factory\OrderFactory;
use App\Repository\OrderRepository;
use App\Service\BillGenerator;
use App\Service\BillMicroserviceClient;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use \Symfony\Component\HttpFoundation\Request; // лишний слэш в начале
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use const App\Entity\CONTRACTOR_TYPE_LEGAL;
use const App\Entity\CONTRACTOR_TYPE_PERSON;

class OrderController
{
    /*
     * Здесь и в остальных файлах
     *
     * С учетом PSR имена свойств и переменных должны быть в camelCase
     * Если PHP 7.4 можем указывать типы свойств при определеннии, а не в докблоках
     * У методов не указан возвращаемый тип
     *
     */

    /** @var OrderFactory */
    protected $order_factory;

    /** @var OrderRepository */
    protected $order_repository;

    public function __construct(OrderFactory $order_factory, OrderRepository $order_repository)
    {
        $this->order_factory = $order_factory;
        $this->order_repository = $order_repository;
    }

    /**
     * @Route("/create", methods={"POST"})
     */
    public function create(Request $request)
    {
        // Я бы использовал DTO и валидацию входящих данных
        $orderData = json_decode($request->getContent(), true);
        $orderId = $this->order_factory->generateOrderId();

        try {
            $order = $this->order_factory->createOrder($orderData, $orderId);

            if ($order->contractorType === CONTRACTOR_TYPE_PERSON) {
                //сохранение заказа происходит в любом случае, есть смысл вынести его из if'ов и не дублировать код
                $this->order_repository->save($order);
                // Т.к. на входе данные в JSON, возможно стоит и на выходе отдавать в нём (ссылка на редирект), нужно уточнить, какое поведение ожидается
                return new RedirectResponse($order->getPayUrl());

            }
            if ($order->contractorType === CONTRACTOR_TYPE_LEGAL) {
                /*
                 * BillGenerator лучше внедрить в конструкторе или использовать доп.сервис для работы с заказом,
                 * чтобы не нарушать принцип единой ответственности, лишней ответственностью у Order
                 * Убрать из Order методы setBillGenerator и getBillUrl
                 * Использовать здесь напрямую экземпляр объекта BillGenerator и передавать в метод generate($order)
                 */
                $order->setBillGenerator(new BillGenerator());
                $this->order_repository->save($order);
                return new RedirectResponse($order->getBillUrl());
            }
        } catch (\Exception $exception) {
            // Тот же вопрос про выходные данные в JSON здесь и далее
            return new Response("Something went wrong");
        }
    }

    /**
     * @Route("/finish/{orderId}", methods={"GET"})
     */
    // неинформативное имя метода
    public function finish($orderId)
    {
        $order = $this->order_repository->get($orderId);
        if ($order->contractorType == CONTRACTOR_TYPE_LEGAL) {
            /*
             * Так как же как и BillGenerator, лучше убрать лишнюю зависимость у Order и передавать ид заказа сразу ->IsPaid($orderId)
             */
            $order->setBillClient(new BillMicroserviceClient());
        }
        /*
         * проверку оплаты вынести во вспомогательный класс, где будет проверка и у физ.лица и у юр.лица, тем самым
         * оставив один метод для проверки, но убрав лишнюю зависимость у сущности заказа
         */
        if ($order->isPaid()) {
            return new Response("Thank you");
        } else {
            return new Response("You haven't paid bill yet");
        }
    }

    /**
     * @Route("/last", methods={"GET"})
     */
    public function last(Request $request)
    {
        $limit = $request->get("limit");
        $orders = $this->order_repository->last($limit);
        return new JsonResponse($orders);
    }
}

