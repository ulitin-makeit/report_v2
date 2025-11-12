<?php

	namespace Brs\Report\Page;

	/**
	 * Данный абстрактный класс устанавливается в обработчики страниц отчёта.
	 */
	abstract class AbstractPage {

		protected $reportEntity;

		/**
		 * Обязательный метод в котором формируются данные отчёта в шаблон обработчика.
		 * 
		 * Всегда принимает orm-объект отчёта.
		 * 
		 */
		abstract function getData(object $reportObject);

		public function getOptions(){}

		/**
		 * @return mixed
		 */
		public function getReportEntity(){
			return $this->reportEntity;
		}

		/**
		 * @param mixed $reportEntity
		 *
		 * @return self
		 */
		public function setReportEntity($reportEntity){

			$this->reportEntity = $reportEntity;

			return $this;

		}

		public function checkRights(){
			return true;
		}

		public function getCustomData(){
			return true;
		}

	}
