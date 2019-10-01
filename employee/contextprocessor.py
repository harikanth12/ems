

def role(request):
	if request.user.is_authenticated:
		request.role = None
		groups = request.user.groups.all()
		if groups:
			request.role = groups[0].name
		return {"role":request.role}