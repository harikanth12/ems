from django.shortcuts import render,reverse,get_object_or_404
from django.contrib.auth.models import User
from django.http import Http404,HttpResponseRedirect
from employee.forms import *
from django.contrib.auth import authenticate,login,logout
from django.contrib.auth.decorators import login_required
from employee.decorators import admin_only
from poll.models import Question
from employee.forms import ProfileForm
from django.core.paginator import Paginator,PageNotAnInteger,EmptyPage
# Create your views here.

def user_login(request):
	context = {}
	context['title'] = "Login Page"
	if request.method == "POST":
		username = request.POST['username']
		password = request.POST['password']
		user = authenticate(request,username=username,password=password)
		if user:
			login(request,user)
			if request.GET.get('next',None):
				print(request.GET['next'])
				return HttpResponseRedirect(request.GET['next'])

			return HttpResponseRedirect(reverse('sucess'))
		else:
			context['error'] = "Please valid credentinals !!!"
			return render(request,'auth/login.html',context)

	else:
		return render(request,'auth/login.html',context)


@login_required(login_url='/login/')
def sucess(request):
	context = {}
	context['user'] = request.user
	return render (request,'auth/sucess.html',context)

def user_logout(request):
	if request.method == "POST":
		logout(request)
		return HttpResponseRedirect(reverse('user_login'))


@login_required(login_url='/login/')
def employee_list(request):
	print(request.role)
	# print(role,"contextprocessor")
	context = {}
	context['title'] = "Employees"
	user = User.objects.all()
	paginator = Paginator(user,5)
	page_number = request.GET.get('page')
	try:
		user = paginator.page(page_number)
	except PageNotAnInteger:
		user = paginator.page(1)
	except EmptyPage:
		user = paginator.page(paginator.num_pages)
		
	context['employeelist'] = user
	question = Question.objects.all().order_by('-created_at')
	context['questions'] = question
	return render(request,'employee/employee_list.html',context)

@login_required(login_url='/login/')
def employee_details(request,id=None):
	context = {}
	context['title'] = "Employee Details Page"
	try:
		user = User.objects.get(id=id)
		context['details'] = user
	except:
		raise Http404

	return render(request,'employee/employee_details.html',context)

@login_required(login_url='/login/')
@admin_only
def add_employees(request):
	context = {}
	context['title'] = "Adding Employees"
	if request.method == "POST":
		user_form = UserForm(request.POST)

		profile = ProfileForm(request.POST,request.FILES)

		context['userform']= user_form
		context['profile']= profile
		if user_form.is_valid() and profile.is_valid():
			user=user_form.save()

			profile = profile.save(commit=False)
			profile.user = user
			profile.save()
			
			return HttpResponseRedirect(reverse('employees_list'))
		else:
			return render(request,'employee/add_employees.html',context)
	else:
		user_form = UserForm()
		profile_form = ProfileForm()
		context['userform']= user_form
		context['profile']=profile_form
	return render(request,'employee/add_employees.html',context)

@login_required(login_url='/login/')
def edit_employee(request,id=None):
	context = {}
	context['title'] = "Update Employee"
	user = get_object_or_404(User,id=id)
	if request.method == "POST":
		user_form = UserForm(request.POST,instance=user)
		profile = ProfileForm(request.POST,request.FILES,instance=user.profile)
		context['userform'] = user_form
		context['profile']= profile
		if user_form.is_valid() and profile.is_valid() :
			user=user_form.save()
			profile = profile.save(commit=False)
			profile.user = user
			profile.save()
			return HttpResponseRedirect(reverse('employees_list'))
		else:
			return render(request,'employee/edit_employee.html',context)
	else:
		user_form = UserForm(instance=user)
		profile_form = ProfileForm(instance=user.profile)
		context['userform'] = user_form
		context['profile'] = profile_form
	return render(request,'employee/edit_employee.html',context)


@login_required(login_url='/login/')
def delete_employee(request,id=None):
	context = {}
	context['title'] = "Delete Employee"
	user = get_object_or_404(User,id=id)
	if request.method == "POST":
		user.delete()
		return HttpResponseRedirect(reverse('employees_list'))
	else:
		context['user']=user
	return render(request,'employee/delete_employee.html',context)


