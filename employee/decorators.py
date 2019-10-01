from django.shortcuts import render,reverse,get_object_or_404
from django.contrib.auth.models import User
from django.http import Http404,HttpResponseRedirect

def admin_only(view_fun):
	def wrap(request,*args,**kwargs):
		if request.role == "Admin":
			return view_fun(request,*args,**kwargs)
		else:
			return HttpResponseRedirect(reverse('employees_list'))

	return wrap