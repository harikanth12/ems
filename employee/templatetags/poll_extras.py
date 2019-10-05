from django import template
from poll.models import Question
from django.contrib.auth.models import User

register = template.Library()

def upper(value):
	return value.upper()

register.filter('upper',upper)


@register.simple_tag
def recent_polls(n=5):
	question = Question.objects.all().order_by('-created_at')
	return question[0:n]

@register.simple_tag
def recent_employee(n=5):
	user = User.objects.all().order_by('-id')
	return user[0:n]


