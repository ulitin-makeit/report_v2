<?php

    namespace Brs\Report;

	use Brs\Report\Model\Orm\ReportTable;

	/**
	 * Класс создаёт базовый объект отчёта в котором формируется и вызывается обработчик из папки "/lib/Page/".
	 */
    class Report {

		protected $id, $code, $title, $handler, $template;

		/**
		 * Создаёт объект отчёта.
		 * 
		 * @param type $orm
		 * @return \static
		 */
		public static function create($orm): object {

			// создаём пустой объект текущего класса
			$object = new static();

			// заполняем его
			$object->setId($orm->getId());
			$object->setCode($orm->getCode());
			$object->setTitle($orm->getTitle());
			$object->setHandler($orm->getHandler());
			$object->setTemplate($orm->getTemplate());

			return $object;

		}

		/**
		 * Устанавливает объект обработчика.
		 * 
		 * @param type $handler
		 * @return boolean
		 * @throws \Exception
		 */
		public function setHandler($handler){

			// если объект не передан, то вызываем ошибку
			if(empty($handler)){

				throw new \Exception('HANDLER_NOT_FOUND', -1);

				return false;

			}

			// формируем путь к классу
			$handlerName = __NAMESPACE__.'\\Page\\'.$handler;

			// если класса не существует, то вызываем ошибку
			if(!class_exists($handlerName)){
				throw new \Exception('HANDLER_NOT_FOUND', -1);
			}

			// вызываем класс
			$this->handler = new $handlerName;

			$this->handler->setReportEntity($this);

		}

		/**
		 * Метод возвращает идентификатор отчёта.
		 * 
		 * @return int
		 */
		public function getId(): int {
			return $this->id;
		}

		public function setId(int $reportId){
			$this->id = $reportId;
		}

		public function getCode(): string {
			return $this->code;
		}

		public function setCode(string $code){
			$this->code = $code;
		}

		/**
		 * Проверяем права доступа.
		 * 
		 * @return boolean
		 */
		public static function hasRight(): bool {
			return true;
		}

		/**
		 * Метод получает список отчётов.
		 * 
		 * @param type $order
		 * @return object
		 */
		public static function getAll($order = ['sort']): object {
			return ReportTable::getList([
				'order' => $order,
			]);
		}

		/**
		 * Метод возвращает объект отчёта (из коллекции ORM).
		 * 
		 * @param type $code
		 * @return boolean || object
		 */
		public static function getByCode($code){

			if(!$code){
				return false;
			}

			$report = ReportTable::getList([
				'order' => ['ID'],
				'filter'=> ['code' => $code],
				'limit' => 1
			])->fetchObject();

			return $report;

		}

		/**
		 * @return mixed
		 */
		public function getTitle(): string {
			return $this->title;
		}

		/**
		 * @param mixed $title
		 */
		public function setTitle(string $title){
			$this->title = $title;
		}

		/**
		 * @return mixed
		 */
		public function getHandler(): object {
			return $this->handler;
		}

		/**
		 * @return mixed
		 */
		public function getTemplate(){
			return $this->template;
		}

		/**
		 * @param mixed $template
		 */
		public function setTemplate($template){
			$this->template = $template;
		}

    }
