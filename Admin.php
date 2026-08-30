<?php namespace App\Controllers;

use App\Models\AddressModel;
use App\Models\ClassesModel;
use App\Models\ClassRecordModel;
use App\Models\ExtraSMSModel;
use App\Models\PackageModel;
use App\Models\SchoolModel;
use App\Models\SmsModel;
use App\Models\StaffModel;
use App\Models\StudentModel;
use App\Models\UserModel;

class Admin extends BaseController
{
	private $log_status = 'Soma_admin_logged_in';

	public function __construct()
	{
		service('request')->setLocale(isset($_COOKIE['lang']) ? $_COOKIE['lang'] : 'en');
	}

	public function _preset()
	{
		$this->session->set('return_url', current_url());
		if ($this->session->get($this->log_status) === null)
		{
			header('location: ' . base_url('admin/login'));
			die();
		}
		else if ($this->session->get('t_lock_status') !== null)
		{
			header('location: ' . base_url('admin/login'));
			die();
		}
	}

	public function index()
	{
		$this->_preset();
		$data['title']         = 'Admin Dashboard';
		$data['subtitle']      = 'Somanet Admin Dashboard';
		$smsRecordModel        = new SmsModel();
		$schoolModel           = new SchoolModel();
		$packageModel          = new PackageModel();
		$classRecord           = new ClassRecordModel();
		$staffModel            = new StaffModel();
		$userModel             = new UserModel();
		$smsRecord             = $smsRecordModel->select('sms_records.id')
			->join('sms_record_recipients sr', 'sms_records.id=sr.sms_record_id')
			->where('sr.status', 1)
			->like('sms_records.created_at', date('Y-m-d'))
			->get()->getResultArray();
		$totalSms              = $smsRecordModel->select('sms_records.id')
			->join('sms_record_recipients sr', 'sms_records.id=sr.sms_record_id')
			->where('sr.status', 1)
			->get()->getResultArray();
		$fromschools           = $smsRecordModel->select('sms_records.id')
			->join('sms_record_recipients sr', 'sms_records.id=sr.sms_record_id')
			->where('sr.status', 1)
			->like('sms_records.created_at', date('Y-m-d'))
			->groupBy('sms_records.school_id')
			->get()->getResultArray();
		$activeSchool          = $schoolModel->select('id')
			->where('status', 1)
			->get()->getResultArray();
		$totalSchool           = $schoolModel->select('id')
			->get()->getResultArray();
		$package               = $packageModel->select('id')
			->get()->getResultArray();
		$activeStudent         = $classRecord->select('id')
			->where('year', date('Y'))
			->get()->getResultArray();
		$activeStaffs          = $staffModel->select('id')
			->get()->getResultArray();
		$users                 = $userModel->select('id')->get()->getResultArray();
		$data['sms_array']     = '[' . $this->get_sms_month(1) . ',
							 ' . $this->get_sms_month(2) . ',
							 ' . $this->get_sms_month(3) . ',
							 ' . $this->get_sms_month(4) . ',
							 ' . $this->get_sms_month(5) . ',
							 ' . $this->get_sms_month(6) . ',
							 ' . $this->get_sms_month(7) . ',
							 ' . $this->get_sms_month(8) . ',
							 ' . $this->get_sms_month(9) . ',
							 ' . $this->get_sms_month(10) . ',
							 ' . $this->get_sms_month(11) . ',
							 ' . $this->get_sms_month(12) . ']';
		$data['recentSchoolS'] = $schoolModel->select('schools.id,schools.name,schools.acronym,schools.phone,schools.email,schools.head_master,schools.logo,schools.country')
			->orderBy('schools.id', 'DESC')
			->get()->getResultArray();
		$data['users']         = count($users);
		$data['first']         = $this->get_school_prommoter(0);
		$data['second']        = $this->get_school_prommoter(1);
		$data['third']         = $this->get_school_prommoter(2);
		$data['fourth']        = $this->get_school_prommoter(3);
		$data['fifth']         = $this->get_school_prommoter(4);
		$data['students']      = count($activeStudent);
		$data['staffs']        = count($activeStaffs);
		$data['sms']           = count($smsRecord);
		$data['totalSms']      = count($totalSms);
		$data['package']       = count($package);
		$data['from_schools']  = count($fromschools);
		$data['schools']       = count($activeSchool);
		$data['totalSchools']  = count($totalSchool);
		$data['page']          = 'dashboard';
		$data['content']       = view('admin/dashboard', $data);
		return view('main_admin', $data);
	}

	public function get_sms_month($month)
	{
		$endDate = date("Y-m-t", strtotime(date('Y-' . str_pad($month,2,'0',STR_PAD_LEFT))));
		$this->_preset();
		$postModel = new SmsModel();
		$mnth      = $postModel->select('sms_records.id,sr.id')
			->join('sms_record_recipients sr', 'sr.sms_record_id=sms_records.id', 'LEFT')
			->where('sr.status', 1)
			->where('sms_records.created_at >=', date('Y-' . str_pad($month,2,'0',STR_PAD_LEFT) . '-1'))
			->where('sms_records.created_at <=', $endDate)
			->get()->getResultArray();
		return count($mnth);
	}

	public function get_school_prommoter($data)
	{
		$classRecord = new ClassRecordModel();
		$classes     = $classRecord->select('class_records.id,count(class_records.student) as students,sc.acronym as school')
			->join('classes cl', 'cl.id=class_records.class')
			->where('class_records.year', date('Y'))
			->join('schools sc', 'sc.id=cl.school_id')
			->groupBy('cl.school_id')
			->orderBy('students', 'DESC')
			->get()->getResultArray();
		$i           = 0;
		foreach ($classes as $class)
		{
			if ($data === $i)
			{
				return $class;
				break;
			}
			$i++;
		}
	}

	public function login()
	{
		$data['email'] = $this->session->getFlashdata('email');
		$data['error'] = $this->session->getFlashdata('error');
		return view('login_admin', $data);
	}

	public function login_pro()
	{
		$model      = new UserModel();
		$email      = $this->request->getPost('email');
		$password   = $this->request->getPost('password');
		$validation = \Config\Services::validation();
		$validation->setRule('email', 'email', 'trim|required');
		$validation->setRule('password', 'password', 'required|min_length[6]');
		if ($validation->run() !== false)
		{
			$this->session->setFlashdata('email', $email);
			if ($this->request->getGet('type', true) == 'ajax')
			{
				echo '{"type":"error","msg":"' . $validation->getError() . '"}';
			}
			else
			{
				$this->session->setFlashdata('error', $validation->getError());
				$this->session->setFlashdata('email', $email);
				echo 'errrrer';
				die();
				return redirect()->to(base_url('admin/login'));
			}
		}
		else
		{
			$result = $model->checkUser($email);
			$this->session->setFlashdata('email', $email);
			if ($result !== null)
			{
				if (password_verify($password, $result->password))
				{
					if ($result->status == 1 || $result->status == 2)
					{
						$data = [
							'soma_admin_name'   => $result->names,
							'soma_admin_email'  => $result->email,
							'soma_admin_id'     => $result->id,
							'soma_admin_status' => $result->status,
							$this->log_status   => true,
						];
						$this->session->set($data);
						$model->updateLogin($result->id);
						if ($this->request->getGet('type', true) == 'ajax')
						{
							echo '{"type":"success","msg":"login done"}';
						}
						else
						{
							return redirect()->to(base_url('admin'));
						}
					}
					else
					{
						if ($this->request->getGet('type', true) == 'ajax')
						{
							echo '{"type":"error","msg":"Account not active"}';
						}
						else
						{
							$this->session->setFlashdata('error', 'Account not active');
							return redirect()->to(base_url('admin/login'));
						}
					}
				}
				else
				{
					if ($this->request->getGet('type', true) == 'ajax')
					{
						echo '{"type":"error","msg":"Password not correct"}';
					}
					else
					{
						$this->session->setFlashdata('error', 'Password not correct');
						return redirect()->to(base_url('admin/login'));
					}
				}
			}
			else
			{
				if ($this->request->getGet('type', true) == 'ajax')
				{
					echo '{"type":"error","msg":"User not found"}';
				}
				else
				{
					$this->session->setFlashdata('error', 'User not found');
					return redirect()->to(base_url('admin/login'));
				}
			}
		}
	}

	public function change_password()
	{
		$oldpwd  = $this->request->getPost('current_password');
		$pwd     = $this->request->getPost('password');
		$userMdl = new UserModel();
		$result  = $userMdl->checkUser($this->session->get('soma_admin_id'), 'id');
		if ($result !== null)
		{
			if (password_verify($oldpwd, $result->password))
			{
				if ($result->status === 1 || $result->status === 2)
				{
					$data = [
						'id'       => $this->session->get('soma_admin_id'),
						'password' => password_hash($pwd, PASSWORD_DEFAULT),
						'status'   => 1,
					];
					try
					{
						$userMdl->save($data);
						$this->session->set('soma_admin_status', 1);
						return $this->response->setJSON(['success' => 'Password changed successfully']);
					}
					catch (\Exception $e)
					{
						return $this->response->setJSON(['error' => 'Oops, Change password failed, please try again later']);
					}
				}
				else
				{
					return $this->response->setJSON(['error' => 'Account not active']);
				}
			}
			else
			{
				return $this->response->setJSON(['error' => 'Current Password not correct']);
			}
		}
	}

	public function logout($msg = null)
	{
		session_destroy();
		$this->session->setFlashdata('error', $msg);
		return redirect()->to(base_url());
	}

	public function add_school()
	{
		$this->_preset();
		$addressModel      = new AddressModel();
		$pModel            = new PackageModel();
		$data['title']     = 'Add new school';
		$data['subtitle']  = 'Create new school';
		$data['page']      = 'add_school';
		$data['provinces'] = $addressModel->getProvince();
		$data['packages']  = $pModel->get()->getResultArray();
		$data['content']   = view('admin/add_school', $data);
		return view('main_admin', $data);
	}

	public function schools()
	{
		$this->_preset();
		$schoolMdl        = new SchoolModel();
		$pkgMdl           = new PackageModel();
		$data['title']    = 'view all schools';
		$data['subtitle'] = 'view schools';
		$data['page']     = 'schools';
		$data['packages'] = $pkgMdl->get()->getResultArray();
		$data['schools']  = $schoolMdl->getSchool()->getResultArray();
		$data['content']  = view('admin/schools', $data);
		return view('main_admin', $data);
	}

	public function extra_sms()
	{
		$this->_preset();
		$extraMdl         = new ExtraSMSModel();
		$data['title']    = 'view all extra SMS';
		$data['subtitle'] = 'view extra SMS';
		$data['page']     = 'extra_sms';
		$schoolMdl        = new SchoolModel();
		$data['schools']  = $schoolMdl->select('id,name')->getSchool()->getResultArray();
		$data['sms']      = $extraMdl->select('extra_sms_records.sms_count,extra_sms_records.created_at,sk.name as school_name,u.names as operator')
			->join('schools sk', 'sk.id=extra_sms_records.school_id')
			->join('users u', 'u.id=extra_sms_records.created_by')
			->get()->getResultArray();
		$data['content']  = view('admin/extra_sms', $data);
		return view('main_admin', $data);
	}

	public function packages()
	{
		$this->_preset();
		$pkgMdl           = new PackageModel();
		$data['title']    = 'view all packages';
		$data['subtitle'] = 'view packages';
		$data['page']     = 'packages';
		$data['packages'] = $pkgMdl->get()->getResultArray();
		$data['content']  = view('admin/packages', $data);
		return view('main_admin', $data);
	}

	public function users()
	{
		$this->_preset();
		$userMdl          = new UserModel();
		$data['title']    = 'view all users';
		$data['subtitle'] = 'view users';
		$data['page']     = 'users';
		$data['users']    = $userMdl->get()->getResultArray();
		$data['content']  = view('admin/users', $data);
		return view('main_admin', $data);
	}

	public function get_package($json = false)
	{
		$pModel = new PackageModel();
		$pkg    = $pModel->get()->getResultArray();

		echo '<option selected disabled>Select packages</option>';
		foreach ($pkg as $item)
		{
			echo "<option value='{$item['id']}'>{$item['title']}</option>";
		}
	}

	public function manipulate_school($id = null)
	{
		$this->_preset();
		$name       = $this->request->getPost('name');
		$acronym    = $this->request->getPost('acronym');
		$phone      = $this->request->getPost('phone');
		$email      = $this->request->getPost('email');
		$headmaster = $this->request->getPost('headmaster');
		$website    = $this->request->getPost('web');
		$package    = $this->request->getPost('package');
		$country    = ucfirst($this->request->getPost('country'));
		$address    = ucfirst($this->request->getPost('address'));

		try
		{
			$schoolMdl = new SchoolModel();
			$data      = [
				'name'        => $name,
				'acronym'     => $acronym,
				'phone'       => $phone,
				'email'       => $email,
				'head_master' => $headmaster,
				'website'     => $website,
				'package'     => $package,
				'country'     => $country,
				'address'     => $address,
				'status'      => 1,
				'created_by'  => $this->session->get('soma_admin_id'),
				'website'     => $website,
			];
			$school_id = $schoolMdl->insert($data);
			//CREATE DEFAULT STAFF ACCOUNT
			$staffMdl         = new StaffModel();
			$head_names       = explode(' ', $headmaster, 2);
			$fname            = $head_names[0];
			$lname            = isset($head_names[1]) ? $head_names[1] : '';
			$default_password = $this->random_password();
			$staffData        = [
				'school_id'  => $school_id,
				'fname'      => $fname,
				'lname'      => $lname,
				'phone'      => $phone,
				'password'   => password_hash($default_password, PASSWORD_DEFAULT),
				'status'     => 2,
				'email'      => $email,
				'post'       => 1,
				'created_by' => $this->session->get('soma_admin_id'),
			];
			$staffMdl->save($staffData);
			//send notification EMAIL and SMS
			$msg  = "Dear $fname, $name is on somanet, you can now login, \nEmail: "
				. $email . "\nPassword: " . $default_password . "\n Thank you";
			$msg2 = "Dear $fname, $name is on somanet, you can now login, \nEmail: "
				. $email . "\nPassword: *******\n Thank you";
//			if ($this->_send_sms($phone, $msg, $result, 1))
            if ($this->sendSMS($phone, $msg, $result))
			{
				//save sent sms
				$smsMdl = new SmsModel();
				$smsMdl->save([
					'school_id'      => $school_id,
					'active_term'    => 0,
					'content'        => $msg2,
					'recipient'      => $phone,
					'recipient_type' => 1,
				]);
			}
			$data     = [
				'name'             => $name,
				'email'            => $email,
				'fname'            => $fname,
				'lname'            => $lname,
				'default_password' => $default_password,
			];
			$html_msg = view('emails/school_creation', $data);
			$this->_send_email($email, 'Welcome on Somanet', $html_msg);
			return $this->response->setJSON(['success' => 'School saved!']);
		}
		catch (\Exception $e)
		{
			return $this->response->setJSON(['error' => 'Error occurred: ' . $e->getMessage()]);
		}
	}

	public function manipulate_package($id = null)
	{
		$this->_preset();
		$id   = $this->request->getPost('fId');
		$name = $this->request->getPost('name');
		$sms  = $this->request->getPost('sms');
		if ($id === '')
		{
			$data = [
				'title'     => $name,
				'sms_limit' => $sms,
			];
		}
		else
		{
			$data = [
				'id'        => $id,
				'title'     => $name,
				'sms_limit' => $sms,
			];
		}
		try
		{
			$pModel = new PackageModel();
			$pModel->save($data);
			return $this->response->setJSON(['success' => 'Package saved']);
		}
		catch (\Exception $e)
		{
			return $this->response->setJSON(['error' => 'Error occurred: ' . $e->getCode()]);
		}
	}
	public function manipulate_extra_sms($id = null)
	{
		$this->_preset();
		$id   = $this->request->getPost('sid');
		$sms  = $this->request->getPost('sms');
		$data = [
			'school_id'  => $id,
			'sms_count'  => $sms,
			'created_by' => $this->session->get('soma_admin_id'),
		];
		try
		{
			$schoolModel = new SchoolModel();
			if ($schoolModel->where('id', $id)->increment('extra_sms', $sms))
			{
				$smsModel = new ExtraSMSModel();
				$smsModel->save($data);
			}
			else
			{
				return $this->response->setJSON(['error' => 'Error occurred: Please try again later']);
			}
			return $this->response->setJSON(['success' => 'SMS Given']);
		}
		catch (\Exception $e)
		{
			return $this->response->setJSON(['error' => 'Error occurred: ' . $e->getMessage()]);
		}
	}

	public function get_single_package($id)
	{
		$this->_preset();
		$pModel = new PackageModel();
		$pack   = $pModel->select('id,title,sms_limit')
			->where('id', $id)->get()->getRowArray();
		echo json_encode($pack);
	}

	public function get_school_package($id)
	{
		$this->_preset();
		$sklPackage = new SchoolModel();
		$pack       = $sklPackage->select('id,package')
			->where('id', $id)->get()->getRowArray();
		echo json_encode($pack);
	}

	public function changeSchoolPackge()
	{
		$this->_preset();
		$sklPackage = new SchoolModel();
		$pack       = $this->request->getPost('package');
		$id         = $this->request->getPost('fId');
		$data       = [
			'id'      => $id,
			'package' => $pack,
		];
		try
		{
			$sklPackage->save($data);
			return $this->response->setJSON(['success' => 'Package Changed']);
		}
		catch (\Exception $e)
		{
			return $this->response->setJSON(['error' => 'Error occurred: ' . $e->getCode()]);
		}
	}

	public function manipulate_user($id = null)
	{
		$this->_preset();
		$name             = $this->request->getPost('name');
		$email            = $this->request->getPost('email');
		$default_password = $this->random_password();
		try
		{
			$userMdl = new UserModel();
			$userMdl->save([
				'names'     => $name,
				'email'     => $email,
				'password'  => password_hash($default_password, PASSWORD_DEFAULT),
				'status'    => 2,
				'privilege' => 1,
			]);
			$data     = [
				'name'             => $name,
				'email'            => $email,
				'default_password' => $default_password,
			];
			$html_msg = view('emails/user_creation', $data);
			$this->_send_email($email, 'Welcome on Somanet', $html_msg);
			return $this->response->setJSON(['success' => 'User saved']);
		}
		catch (\Exception $e)
		{
			return $this->response->setJSON(['error' => 'Error occurred: ' . $e->getCode()]);
		}
	}

	public function delete_package()
	{
		$this->_preset();
		$id = $this->request->getPost('data');
		try
		{
			$pModel    = new PackageModel();
			$schoolMdl = new SchoolModel();
			$res       = $schoolMdl->where('package', $id)->get(1)->getRowArray();
			if (is_array($res))
			{
				//package can not be deleted because it is used
				return $this->response->setJSON(['error' => 'Oops, Package can not be deleted because it is used on school']);
			}
			$pModel->delete($id);
			return $this->response->setJSON(['success' => 'Package deleted']);
		}
		catch (\Exception $e)
		{
			return $this->response->setJSON(['error' => 'Error occurred: ' . $e->getMessage()]);
		}
	}

	public function delete_user()
	{
		$this->_preset();
		$id = $this->request->getPost('data');
		try
		{
			$userMdl   = new UserModel();
			$schoolMdl = new SchoolModel();
			$res       = $schoolMdl->where('created_by', $id)->get(1)->getRowArray();
			if (is_array($res))
			{
				//package can not be deleted because it is used
				return $this->response->setJSON(['error' => 'Oops, User can not be deleted because He is needed by the system']);
			}
			$userMdl->delete($id);
			return $this->response->setJSON(['success' => 'User deleted']);
		}
		catch (\Exception $e)
		{
			return $this->response->setJSON(['error' => 'Error occurred: ' . $e->getMessage()]);
		}
	}

	public function delete_school()
	{
		$this->_preset();
		$id = $this->request->getPost('data');
		try
		{
			$schoolMdl = new SchoolModel();

			//delete school
			$schoolMdl->delete($id);
			//delete staff
			$staffMdl = new StaffModel();
			$staffMdl->where('school_id', $id)->delete();

			//delete student
			$studentMdl = new StudentModel();
			$studentMdl->where('school_id', $id)->delete();

			return $this->response->setJSON(['success' => 'School deleted']);
		}
		catch (\Exception $e)
		{
			return $this->response->setJSON(['error' => 'Error occurred: ' . $e->getMessage()]);
		}
	}

	public function testSMS()
	{
		if ($this->sendSMS('250785753712', 'Test by QONICS INC on ' . date('Y-m-d H:i:s') . ' from SOMANET', $result))
		{
			echo 'SMS SENT <br>CODE: ' . $result['code'] . '<br>CONTENT: ' . $result['content'];
		}
		else
		{
			echo 'Oops, SMS NOT SENT, code: ' . $result['code'] . '<br>CONTENT: ' . $result['content'];
			;
		}
	}

	public function testEmail()
	{
		if ($this->_send_email('allyblaise@yahoo.co.uk', 'Test from Somanet', 'Test by QONICS INC on ' . date('Y-m-d H:i:s') . ' from SOMANET'))
		{
			echo 'EMAIL SENT';
		}
		else
		{
			echo 'Oops, EMAIL NOT SENT';
		}
	}

	function test()
	{
		$default_password = $this->random_password();
		echo $default_password;
	}
}
