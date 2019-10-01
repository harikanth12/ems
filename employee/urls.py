from django.conf.urls import url
from employee.views import *



urlpatterns = [
    url(r'^list/', employee_list,name='employees_list'),
    url(r'^(?P<id>\d{1,8})/details/',employee_details,name='employees_details'),
    url(r'^add/',add_employees,name='add_employees'),
    url(r'^(?P<id>\d{1,8})/edit/',edit_employee,name='edit_employees'),
    url(r'^(?P<id>\d{1,8})/delete/',delete_employee,name='delete_employees')
]