

# Load libraries
import pandas
import numpy

from sklearn import model_selection
from sklearn.metrics import classification_report
from sklearn.metrics import confusion_matrix
from sklearn.metrics import accuracy_score
from sklearn.linear_model import LogisticRegression
from sklearn.tree import DecisionTreeClassifier
from sklearn.neighbors import KNeighborsClassifier
from sklearn.discriminant_analysis import LinearDiscriminantAnalysis
from sklearn.naive_bayes import GaussianNB
from sklearn.svm import SVC

names = ['hmgs', 'hmgt', 'hstr','amgs', 'amgt', 'astr', 'posdiff', 'md', 'resh', 'resa']

dataset = pandas.read_csv('data.csv', names=names)
data_array = dataset.values

input = data_array[:,0:7]
homegoals = data_array[:,8]
awaygoals = data_array[:,9]

seed = 7
validation_size = 0.2
input_train, input_validation, home_train, home_validation = model_selection.train_test_split(input, homegoals, test_size=validation_size, random_state=seed)
input_train, input_validation, away_train, away_validation = model_selection.train_test_split(input, awaygoals, test_size=validation_size, random_state=seed)



homelda = LinearDiscriminantAnalysis()
homelda.fit(input_train, home_train)
awaylda = LinearDiscriminantAnalysis()
awaylda.fit(input_train, away_train)

bet_dataset = pandas.read_csv('validation.csv', names=names)
bet_array = bet_dataset.values
data = bet_array[:,0:7]

homebets = homelda.predict(data);
awaybets = awaylda.predict(data);


results = numpy.column_stack((homebets, awaybets))

numpy.savetxt("results.csv", results.astype(int), fmt='%i', delimiter=",")
