from django.shortcuts import render,get_object_or_404,reverse,redirect
from poll.models import *
from django.http import HttpResponse,Http404,HttpResponseRedirect
from django.contrib.auth.decorators import login_required


# Create your views here.
@login_required(login_url='/login/')
def index(request):
	context  = {}
	question = Question.objects.all()
	context['title'] = "Polls"
	context['questions'] = question
	return render(request,'polls/index.html',context)

@login_required(login_url='/login/')
def details(request,id=None):
	context  = {}
	question = get_object_or_404(Question,id=id)
	context['title'] = "Details Page"
	context['questions'] = question
	return render(request,'polls/details.html',context)

@login_required(login_url='/login/')
def singlepoll(request,id=None):
	question = Question.objects.get(id=id)
	if request.method == "GET":
		context  = {}
		
		context['title'] = "Voting Page"
		context['questions'] = question
		return render(request,'polls/poll.html',context)

	if request.method == "POST":
		data = request.POST
		print(data)
		try:
			choice_id=data['choice']
			print(choice_id)
			user_id = question.created_by.id
			print(user_id,"userid")
			answer = Answer.objects.create(user_id=user_id,choices_id=choice_id)
			# return redirect('polls_details',id=id)
			return redirect('polls_list')
		except Exception as e:
			print(e)
			return HttpResponse("You have not done sucessfully")