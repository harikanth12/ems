from django.conf.urls import url
from poll.views import *



urlpatterns = [
    url(r'^list/', index,name='polls_list'),
    url(r'^(?P<id>\d)/details/',details,name='polls_details'),
    url(r'^(?P<id>\d)/',singlepoll,name='singlepoll')
]
