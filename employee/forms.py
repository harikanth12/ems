from django import forms
from django.contrib.auth.models import User,Group
from employee.models import Profile

class UserForm(forms.ModelForm):
	password = forms.CharField(widget=forms.PasswordInput)
	role = forms.ModelChoiceField(queryset=Group.objects.all())

	class Meta: 
		model = User
		fields = ['first_name','last_name','email','password','username','role']

		label = {'password':'Password'}

	def __init__(self,*args,**kwargs):
		print(kwargs,"inital")
		if kwargs.get('instance'):
			initial = kwargs.setdefault('initial',{})
			# print(kwargs)
			# print(kwargs['instance'].groups.all())
			if kwargs['instance'].groups.all():
				initial['role'] = kwargs['instance'].groups.all()[0]
				print(initial['role'])
			else:
				initial['role'] = None

		forms.ModelForm.__init__(self,*args,**kwargs)

	def save(self):
		password = self.cleaned_data.pop('password')
		role = self.cleaned_data.pop('role')
		user = super().save()
		user.groups.set([role])
		user.set_password(password)
		user.save()
		return user
		
class ProfileForm(forms.ModelForm):
	class Meta:
		model = Profile
		fields = ['profile_picture',]